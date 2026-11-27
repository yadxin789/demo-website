<?php

declare(strict_types=1);

namespace App\Modules\Mes\Services\Import;

use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use RuntimeException;

/**
 * 表格读取（M1-11）：把上传的 xlsx / csv 读成二维数组。
 *
 * 只支持这两种格式。xls（97-2003）与 ods 一律拒绝，避免引入额外依赖与乱码风险。
 * 中文 CSV 常见是 Excel 导出的 GBK，这里做一次编码探测。
 */
class SheetReader
{
    public const MAX_ROWS = 2000;

    /**
     * @return array<int, array<int, string>> 含表头，行内已 trim
     */
    public function rows(string $path, string $extension): array
    {
        $ext = strtolower($extension);

        if ($ext === 'csv') {
            $reader = new CsvReader();
            $raw = (string) file_get_contents($path);
            $reader->setInputEncoding(mb_check_encoding($raw, 'UTF-8') ? 'UTF-8' : 'GBK');
        } elseif ($ext === 'xlsx') {
            $reader = new XlsxReader();
        } else {
            throw new RuntimeException('只支持 .xlsx 与 .csv 两种格式。');
        }

        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, false);

        $rows = [];
        foreach ($raw as $row) {
            $cells = array_map(
                static fn ($v) => is_string($v) ? trim($v) : (string) $v,
                $row
            );
            // 整行为空的跳过（Excel 末尾常有大量空行）
            if (implode('', $cells) === '') {
                continue;
            }
            $rows[] = $cells;
            if (count($rows) > self::MAX_ROWS) {
                throw new RuntimeException('单次最多导入 ' . self::MAX_ROWS . ' 行，请拆分文件。');
            }
        }

        return $rows;
    }

    /**
     * 表头定位：按给定列名数组，返回 [列名 => 列下标]。
     *
     * 少了任何一列都在开始前就报错，避免"读到一半发现第 7 列对不上"。
     *
     * @param  array<int, string>  $required
     * @return array<string, int>
     */
    public function headerMap(array $headerRow, array $required): array
    {
        $map = [];
        foreach ($headerRow as $index => $cell) {
            $key = trim((string) $cell);
            if ($key !== '' && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }

        $missing = array_values(array_filter($required, fn (string $col) => ! isset($map[$col])));
        if ($missing !== []) {
            throw new RuntimeException('模板列不匹配，缺少：' . implode('、', $missing) . '。请下载最新模板重新填写。');
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int>  $map
     * @return array<string, string>
     */
    public function pick(array $cells, array $map): array
    {
        $out = [];
        foreach ($map as $col => $index) {
            $out[$col] = $cells[$index] ?? '';
        }

        return $out;
    }
}
