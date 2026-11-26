<?php

namespace App\Modules\Shop\Services\Exchange;

use App\Modules\Shop\Exceptions\ImportException;
use Illuminate\Http\UploadedFile;

/**
 * CSV 读取器。
 *
 * 处理要点：
 * - 只收 .csv / .txt，单文件 ≤ 5 MB、≤ 5000 行，避免一次性吃满内存；
 * - 自动识别 UTF-8（含 BOM）与 GBK/GB18030，统一转 UTF-8 后解析，解决 Excel 中文乱码；
 * - 返回真实行号（表头为第 1 行，首条数据为第 2 行），空行跳过但行号不偏移。
 */
class CsvReader
{
    public const MAX_BYTES = 5242880;

    public const MAX_ROWS = 5000;

    /**
     * @return array{header: array<int, string>, rows: array<int, array{line: int, cells: array<int, string>}>}
     */
    public function read(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new ImportException('文件上传失败，请重新选择文件');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt'], true)) {
            throw new ImportException('只支持 .csv 文件，请在 Excel 中另存为「CSV UTF-8」后再上传');
        }

        $size = (int) $file->getSize();
        if ($size <= 0) {
            throw new ImportException('文件为空，请重新选择');
        }
        if ($size > self::MAX_BYTES) {
            throw new ImportException('文件不能超过 5 MB，请拆分后再导入');
        }

        $raw = @file_get_contents($file->getRealPath());
        if ($raw === false || $raw === '') {
            throw new ImportException('无法读取文件内容，请重新选择');
        }

        return $this->parse($this->toUtf8($raw));
    }

    /**
     * 非 UTF-8 内容按 GB18030 转码（GBK 的超集），仍无法识别则拒绝。
     */
    private function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'GB18030');
            if ($converted === false || ! mb_check_encoding($converted, 'UTF-8')) {
                throw new ImportException('文件编码无法识别，请另存为 UTF-8 或 GBK 编码的 CSV 后再导入');
            }
            $raw = $converted;
        }

        return str_replace(["\r\n", "\r"], "\n", $raw);
    }

    /**
     * @return array{header: array<int, string>, rows: array<int, array{line: int, cells: array<int, string>}>}
     */
    private function parse(string $raw): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $header = null;
        $rows = [];
        $record = 0;

        while (($cells = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $record++;
            $cells = array_map(
                static fn ($cell) => is_string($cell) ? trim($cell) : '',
                $cells
            );

            if ($this->isBlank($cells)) {
                continue;
            }

            if ($header === null) {
                $header = $cells;
                continue;
            }

            $rows[] = ['line' => $record, 'cells' => $cells];

            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);

                throw new ImportException('单次最多导入 '.self::MAX_ROWS.' 行，请拆分文件后分批导入');
            }
        }

        fclose($handle);

        if ($header === null) {
            throw new ImportException('文件内容为空，请先下载模板填写数据');
        }
        if ($rows === []) {
            throw new ImportException('文件中没有数据行，请检查是否只填写了表头');
        }

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== '') {
                return false;
            }
        }

        return true;
    }
}
