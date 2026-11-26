<?php

/**
 * 探针脚本：核对各产品库的表与基础状态。
 * 用法：php tools/probe-pm.php [连接名]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$connection = $argv[1] ?? 'pm';

echo "连接: {$connection}\n";

try {
    $pdo = Illuminate\Support\Facades\DB::connection($connection)->getPdo();
    $driver = Illuminate\Support\Facades\DB::connection($connection)->getDriverName();
    $dbName = Illuminate\Support\Facades\DB::connection($connection)->getDatabaseName();
    echo "driver: {$driver}\n";
    echo "database: {$dbName}\n";

    $tables = Illuminate\Support\Facades\DB::connection($connection)
        ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

    echo '表数量: '.count($tables)."\n";
    $names = array_map(fn ($t) => $t->name, $tables);
    echo implode(', ', $names)."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    exit(1);
}
