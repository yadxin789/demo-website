<?php

namespace App\Modules\Shop\Http\Controllers\System;

use App\Modules\Shop\Exceptions\BackupException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Services\System\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 本地备份与还原：列表、创建、还原（还原需二次确认，仅系统管理员可执行）。
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups)
    {
    }

    public function index(): View
    {
        return view('shop::system.backups.index', [
            'backups' => $this->backups->list(),
            'maxFiles' => (int) config('shop.backup.max_files', 50),
            'maxSizeMb' => (int) config('shop.backup.max_size_mb', 512),
            'database' => (string) config('database.connections.mysql.database'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:60'],
        ], [], ['note' => '备注']);

        try {
            $result = $this->backups->create((string) ($data['note'] ?? ''), $request->user()?->id);
        } catch (BackupException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('shop.backups.index')
            ->with('status', '备份已创建：' . $result['name'] . '（' . $this->humanSize($result['size']) . '）。');
    }

    public function restore(Request $request, string $backup): RedirectResponse
    {
        $confirmed = $request->boolean('confirm');

        try {
            $result = $this->backups->restore($backup, $confirmed, $request->user()?->id);
        } catch (BackupException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('shop.backups.index')
            ->with('status', '已从 ' . basename($backup) . ' 还原：数据库 ' . $this->humanSize($result['database_size'])
                . '，本地文件 ' . $result['files'] . ' 个。');
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
