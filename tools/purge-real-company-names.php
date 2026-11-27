<?php

/**
 * 真实企业名清理：扫描各库所有文本列，把真实/疑似真实企业名替换为虚构演示名。
 *
 * 注意：SQLite 下 `replace(col, ?, ?)` 这类带绑定参数的表达式更新会静默 0 行，
 * 所以这里取行 → PHP 端 str_replace → 按主键整值回写。
 *
 * 用法：
 *   php tools/purge-real-company-names.php            # 只扫描，打印命中位置
 *   php tools/purge-real-company-names.php --apply    # 实际替换
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv, true);

$map = [
    '深圳华信电子有限公司' => '演示精密电子（深圳）有限公司',
    '东莞创科科技有限公司' => '演示智造科技（东莞）有限公司',
    '广州明远贸易有限公司' => '演示商贸（广州）有限公司',
    '国巨电子（中国）有限公司' => '演示元器件（中国）有限公司',
    '村田电子贸易有限公司' => '演示被动元件贸易有限公司',
    '深圳世强先进科技有限公司' => '演示半导体代理（深圳）有限公司',
    '深圳华强PCB板厂' => '演示PCB制造厂',
    '华南食品供应链有限公司' => '演示食品供应链（华南）有限公司',
    '华东日化贸易有限公司' => '演示日化贸易（华东）有限公司',
    '岭南粮油有限公司' => '演示粮油（华南）有限公司',
    '柬埔寨金边制衣厂' => '演示海外制衣厂',
    '深圳华信电子' => '演示精密电子',
    '东莞创科科技' => '演示智造科技',
    '广州明远贸易' => '演示商贸',
    '国巨电子' => '演示元器件',
    '村田电子' => '演示被动元件',
    '世强先进' => '演示半导体代理',
    '华强PCB' => '演示PCB',
    '华南食品供应链' => '演示食品供应链',
    '华东日化贸易' => '演示日化贸易',
    '岭南粮油' => '演示粮油',
];

$conns = ['erp', 'pm', 'shop', 'mes', 'hr'];
$totalHits = 0;
$totalRows = 0;
$skipColumns = ['password', 'remember_token', 'api_key', 'app_secret', 'token', 'encoding_aes_key', 'private_key', 'mfa_secret', 'mfa_recovery_codes'];

foreach ($conns as $conn) {
    $tables = DB::connection($conn)->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    foreach ($tables as $t) {
        $table = $t->name;
        $cols = Schema::connection($conn)->getColumnListing($table);
        $keys = Schema::connection($conn)->getIndexes($table);
        $pkCol = 'id';
        if (! in_array($pkCol, $cols, true)) {
            $pkCol = $cols[0] ?? null;
        }
        foreach ($cols as $col) {
            if (in_array($col, $skipColumns, true)) {
                continue;
            }
            $type = strtolower((string) Schema::connection($conn)->getColumnType($table, $col));
            if (! str_contains($type, 'char') && ! str_contains($type, 'text') && ! str_contains($type, 'clob')) {
                continue;
            }
            $rows = DB::connection($conn)->table($table)->where(function ($q) use ($col, $map) {
                foreach ($map as $from => $_) {
                    $q->orWhere($col, 'like', "%{$from}%");
                }
            })->get([$pkCol, $col]);

            if ($rows->isEmpty()) {
                continue;
            }

            $totalHits += $rows->count();
            echo "{$conn}.{$table}.{$col}（{$rows->count()} 行）\n";
            if (! $apply) {
                continue;
            }
            foreach ($rows as $row) {
                $old = (string) $row->{$col};
                $new = strtr($old, $map);
                if ($new === $old) {
                    continue;
                }
                DB::connection($conn)->table($table)->where($pkCol, $row->{$pkCol})->update([$col => $new]);
                $totalRows++;
                echo "  {$old} → {$new}\n";
            }
        }
    }
}

echo $apply
    ? "已替换 {$totalRows} 行（涉及 {$totalHits} 行命中）\n"
    : "扫描到 {$totalHits} 行命中（未替换，加 --apply 执行）\n";
