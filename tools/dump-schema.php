<?php

/**
 * 导出指定表的结构与行数（用于补种演示数据时对齐列名/外键）。
 *
 *   php tools/dump-schema.php erp customers suppliers materials ...
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$conn = $argv[1] ?? 'erp';
$tables = array_slice($argv, 2);
$db = DB::connection($conn);

if (! $tables) {
    $tables = collect($db->select("select name from sqlite_master where type='table' and name not like 'sqlite_%'"))
        ->pluck('name')->all();
}

foreach ($tables as $t) {
    if (! $db->getSchemaBuilder()->hasTable($t)) {
        echo "[{$t}] 不存在" . PHP_EOL;
        continue;
    }
    $count = $db->table($t)->count();
    echo "[{$conn}.{$t}] 行数={$count}" . PHP_EOL;
    echo '    列: ' . implode(', ', $db->getSchemaBuilder()->getColumnListing($t)) . PHP_EOL;

    if ($count > 0 && $count <= 6) {
        foreach ($db->table($t)->limit(3)->get() as $row) {
            echo '    样例: ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
    } elseif ($count > 0) {
        $first = $db->table($t)->limit(1)->first();
        echo '    首行: ' . json_encode($first, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
