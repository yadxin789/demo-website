<?php

declare(strict_types=1);

/**
 * 生成 MES 导入验收用的三个 xlsx（M1-11~M1-14 自测）。
 *
 * 物料：2 行合法 + 1 行编码格式错，用来验证"坏行拦住、好行照常"
 * BOM：2 行（同一 BOM 两个组件）
 * 工单：1 行
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

@mkdir(__DIR__ . '/../storage/tests', 0777, true);

function make(string $file, array $header, array $rows): void
{
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->fromArray($header, null, 'A1');
    $sheet->fromArray($rows, null, 'A2');
    (new Xlsx($book))->save(__DIR__ . '/../storage/tests/' . $file);
    echo "  写入 storage/tests/{$file}（" . count($rows) . " 行数据）\n";
}

make('materials.xlsx', ['物料编码', '名称', '规格', '单位', '类别', '安全库存', '现有库存', '标准成本'], [
    ['MJ-RM-901', '铝型材 6063', '30×30×1000', 'M', '原料', '200', '800', '36.5'],
    ['MJ-FG-901', '演示成品 A', '标准款', 'PCS', '成品', '20', '35', '1280'],
    ['MAT-902', '编码前缀写错的料', '规格 X', 'PCS', '原料', '0', '0', '0'],
]);

make('boms.xlsx', ['BOM编码', '产品编码', '版本', '组件编码', '单件用量', '损耗率', '行序'], [
    ['BOM-FG-901', 'MJ-FG-901', 'V1', 'MJ-RM-901', '2', '0.02', '10'],
    ['BOM-FG-901', 'MJ-FG-901', 'V1', 'MJ-SM-001', '1', '0.01', '20'],
]);

make('work-orders.xlsx', ['工单号', '产品编码', '数量', '计划开工', '计划完工', '优先级', '客户'], [
    ['WO-2026-901', 'MJ-FG-001', '60', '2026-12-01', '2026-12-05', '5', '演示客户'],
]);
