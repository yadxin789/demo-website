<?php

namespace App\Modules\Shop\Services\System;

use App\Modules\Shop\Exceptions\BackupException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * 本地备份与还原：数据库导出 + 本地磁盘文件打成单个 zip。
 *
 * 备份包结构：
 *   database.sql     数据库完整导出
 *   manifest.json    版本/时间/备注/文件清单/数据库 sha256
 *   files/{disk}/…   本地磁盘文件（如商品图片）
 *
 * 还原前一律校验：文件名白名单、zip 可读、manifest 完整、database.sql 存在且 sha256 一致、
 * 拒绝 `..`/绝对路径等 zip 穿越条目；还原必须显式确认。
 * 备份创建与还原都会写审计，并在备份目录追加一行 JSON 日志（文件留痕）。
 */
class BackupService
{
    public const DISK = 'backups';

    public const LOG_FILE = 'backup.log';

    private const NAME_PATTERN = '/^backup-\d{8}-\d{6}-[a-z0-9]{6}\.zip$/';

    public function __construct(
        private readonly DatabaseDumper $dumper,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * 创建备份包，返回 manifest 与包大小。
     *
     * @return array<string, mixed>
     */
    public function create(string $note = '', ?int $operatorId = null): array
    {
        $disk = $this->disk();
        $disk->makeDirectory('');
        $this->prune();

        $name = 'backup-' . now()->format('Ymd-His') . '-' . Str::lower(Str::random(6)) . '.zip';        $tmpDir = $this->tempDir();

        try {
            $sql = $tmpDir . '/database.sql';
            $this->dumper->dump($sql);

            if (! is_file($sql) || filesize($sql) === 0) {
                throw new BackupException('数据库导出为空，备份中止');
            }

            $files = $this->collectFiles();

            $manifest = [
                'version' => 1,
                'name' => $name,
                'created_at' => now()->format('Y-m-d\TH:i:s.uP'),
                'note' => $note,
                'operator_id' => $operatorId,
                'app' => (string) config('shop.name', '百货零售ERP'),
                'database' => [
                    'driver' => (string) config('database.default'),
                    'name' => (string) config('database.connections.mysql.database'),
                    'size' => filesize($sql),
                    'sha256' => hash_file('sha256', $sql),
                ],
                'files' => $files,
            ];

            $zip = new ZipArchive();
            $zipPath = $disk->path($name);

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new BackupException('无法创建备份包（zip 打开失败）');
            }

            $zip->addFile($sql, 'database.sql');
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            foreach ($files as $file) {
                $source = Storage::disk($file['disk'])->path($file['path']);

                if (is_file($source)) {
                    $zip->addFile($source, 'files/' . $file['disk'] . '/' . $file['path']);
                }
            }

            $zip->close();
        } finally {
            $this->deleteDirectory($tmpDir);
        }

        $size = (int) $disk->size($name);
        $maxBytes = (int) config('shop.backup.max_size_mb', 512) * 1024 * 1024;

        if ($size > $maxBytes) {
            $disk->delete($name);

            throw new BackupException('备份包超过 ' . config('shop.backup.max_size_mb', 512) . ' MB 上限，已删除本次备份');
        }

        $this->audit->record([
            'module' => 'system',
            'action' => AuditLog::ACTION_CREATE,
            'model' => self::class,
            'document_no' => $name,
            'new_value' => [
                'name' => $name,
                'size' => $size,
                'database_size' => $manifest['database']['size'],
                'files' => count($manifest['files']),
                'note' => $note,
            ],
            'message' => '创建本地备份 ' . $name,
        ]);

        $this->appendLog('create', $name, [
            'size' => $size,
            'files' => count($manifest['files']),
            'note' => $note,
        ]);

        return [
            'name' => $name,
            'size' => $size,
            'manifest' => $manifest,
        ];
    }

    /**
     * 备份清单（新→旧），无法解析的包跳过并标注。
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $items = [];

        foreach ($this->disk()->files('') as $path) {
            $name = basename($path);

            if (! preg_match(self::NAME_PATTERN, $name)) {
                continue;
            }

            $entry = [
                'name' => $name,
                'size' => (int) $this->disk()->size($name),
                'created_at' => null,
                'note' => '',
                'files' => 0,
                'database_size' => 0,
                'valid' => false,
            ];

            try {
                $manifest = $this->readManifest($name);
                $entry['created_at'] = $manifest['created_at'] ?? null;
                $entry['note'] = (string) ($manifest['note'] ?? '');
                $entry['files'] = count($manifest['files'] ?? []);
                $entry['database_size'] = (int) ($manifest['database']['size'] ?? 0);
                $entry['valid'] = true;
            } catch (BackupException) {
                // 损坏包仍列出，便于人工处理；还原时会再次校验并拒绝
            }

            $items[] = $entry;
        }

        usort($items, function ($a, $b) {
            $byTime = strcmp((string) $b['created_at'], (string) $a['created_at']);

            return $byTime !== 0 ? $byTime : strcmp((string) $b['name'], (string) $a['name']);
        });

        return array_slice($items, 0, (int) config('shop.backup.max_files', 50));
    }

    /**
     * 还原前的完整校验；通过返回 manifest。
     *
     * @return array<string, mixed>
     */
    public function validate(string $name): array
    {
        $name = basename($name);

        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw new BackupException('备份文件名不合法');
        }

        if (! $this->disk()->exists($name)) {
            throw new BackupException('备份包不存在或已被删除');
        }

        $zip = new ZipArchive();

        if ($zip->open($this->disk()->path($name)) !== true) {
            throw new BackupException('备份包无法打开，可能已损坏');
        }

        try {
            $entries = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entries[] = (string) $zip->getNameIndex($i);
            }

            foreach ($entries as $entry) {
                if (str_contains($entry, '..') || str_starts_with($entry, '/') || str_contains($entry, '\\')) {
                    throw new BackupException('备份包包含非法路径条目，已拒绝还原：' . $entry);
                }
            }

            if (! in_array('manifest.json', $entries, true) || ! in_array('database.sql', $entries, true)) {
                throw new BackupException('备份包缺少 manifest.json 或 database.sql');
            }

            $manifestJson = $zip->getFromName('manifest.json');
            $sql = $zip->getFromName('database.sql');

            if ($manifestJson === false || $sql === false) {
                throw new BackupException('备份包内容读取失败');
            }

            $manifest = json_decode($manifestJson, true);

            if (! is_array($manifest) || ($manifest['version'] ?? null) !== 1) {
                throw new BackupException('备份包版本不受支持');
            }

            if (($manifest['database']['sha256'] ?? null) !== hash('sha256', $sql)) {
                throw new BackupException('数据库导出校验失败（sha256 不匹配），备份包可能被篡改');
            }
        } finally {
            $zip->close();
        }

        return $manifest;
    }

    /**
     * 还原备份：先校验，再还原数据库，最后覆盖本地文件。
     *
     * @return array{files: int, database_size: int}
     */
    public function restore(string $name, bool $confirmed, ?int $operatorId = null): array
    {
        if (! $confirmed) {
            throw new BackupException('还原会覆盖当前数据库与本地文件，请先确认再执行');
        }

        $name = basename($name);
        $manifest = $this->validate($name);

        $tmpDir = $this->tempDir();
        $zip = new ZipArchive();

        try {
            if ($zip->open($this->disk()->path($name)) !== true) {
                throw new BackupException('备份包无法打开，可能已损坏');
            }

            if (! $zip->extractTo($tmpDir)) {
                throw new BackupException('备份包解压失败');
            }

            $zip->close();

            $sql = $tmpDir . '/database.sql';

            if (! is_file($sql) || hash_file('sha256', $sql) !== ($manifest['database']['sha256'] ?? '')) {
                throw new BackupException('解压后的数据库导出校验失败');
            }

            $this->dumper->restore($sql);

            $restored = 0;
            foreach (($manifest['files'] ?? []) as $file) {
                $source = $tmpDir . '/files/' . $file['disk'] . '/' . $file['path'];

                if (! is_file($source)) {
                    continue;
                }

                $target = Storage::disk((string) $file['disk'])->path((string) $file['path']);
                if (! is_dir(dirname($target))) {
                    mkdir(dirname($target), 0775, true);
                }

                copy($source, $target);
                $restored++;
            }
        } finally {
            $this->deleteDirectory($tmpDir);
        }

        $this->audit->record([
            'module' => 'system',
            'action' => AuditLog::ACTION_APPROVE,
            'model' => self::class,
            'document_no' => $name,
            'old_value' => ['restored_from' => $name],
            'new_value' => [
                'database_size' => (int) ($manifest['database']['size'] ?? 0),
                'files' => $restored,
            ],
            'message' => '从备份还原 ' . $name,
        ]);

        $this->appendLog('restore', $name, ['files' => $restored]);

        return [
            'files' => $restored,
            'database_size' => (int) ($manifest['database']['size'] ?? 0),
        ];
    }

    /** 备份数量超出上限时删除最旧的包（保留最近 max_files 个）。 */
    private function prune(): void
    {
        $names = [];

        foreach ($this->disk()->files('') as $path) {
            $name = basename($path);
            if (preg_match(self::NAME_PATTERN, $name)) {
                $names[] = $name;
            }
        }

        $max = max(1, (int) config('shop.backup.max_files', 50));

        if (count($names) < $max) {
            return;
        }

        sort($names);
        foreach (array_slice($names, 0, count($names) - $max + 1) as $old) {
            $this->disk()->delete($old);
        }
    }

    /**
     * 需要打包的本地文件清单。
     *
     * @return array<int, array{disk: string, path: string, size: int}>
     */
    private function collectFiles(): array
    {
        $files = [];

        foreach ((array) config('shop.backup.file_disks', []) as $diskName) {
            $sourceDisk = Storage::disk((string) $diskName);

            foreach ($sourceDisk->allFiles() as $path) {
                $files[] = [
                    'disk' => (string) $diskName,
                    'path' => $path,
                    'size' => (int) $sourceDisk->size($path),
                ];
            }
        }

        return $files;
    }

    /** @return array<string, mixed> */
    private function readManifest(string $name): array
    {
        $zip = new ZipArchive();

        if ($zip->open($this->disk()->path($name)) !== true) {
            throw new BackupException('备份包无法打开');
        }

        try {
            $json = $zip->getFromName('manifest.json');
        } finally {
            $zip->close();
        }

        $manifest = $json === false ? null : json_decode($json, true);

        if (! is_array($manifest)) {
            throw new BackupException('备份包 manifest 缺失或损坏');
        }

        return $manifest;
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('shop.backup.disk', self::DISK));
    }

    private function tempDir(): string
    {
        $dir = storage_path('app/backup-tmp/' . Str::uuid());

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new BackupException('无法创建备份临时目录');
        }

        return $dir;
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    /** @param  array<string, mixed>  $extra */
    private function appendLog(string $event, string $name, array $extra): void
    {
        $line = json_encode(['at' => now()->toIso8601String(), 'event' => $event, 'name' => $name] + $extra, JSON_UNESCAPED_UNICODE);

        try {
            $this->disk()->append(self::LOG_FILE, $line . "\n");
        } catch (\Throwable) {
            // 日志失败不影响主流程
        }
    }
}
