<?php

declare(strict_types=1);

/** 探列名：php tools/cols.php hr leaves requisitions / php tools/cols.php mes work_orders */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$args = array_slice($argv, 1);
$conn = $args[0] ?? 'hr';
$tables = array_slice($args, 1);

foreach ($tables as $t) {
    try {
        $cols = DB::connection($conn)->select("pragma table_info({$t})");
        echo "== {$conn}.{$t}\n";
        foreach ($cols as $c) {
            echo '   ' . $c->name . '  ' . $c->type . "\n";
        }
        $row = DB::connection($conn)->table($t)->first();
        if ($row !== null) {
            echo '   首行：' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
        }
    } catch (Throwable $e) {
        echo "!! {$conn}.{$t} " . $e->getMessage() . "\n";
    }
}
