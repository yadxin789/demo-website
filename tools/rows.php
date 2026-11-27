<?php
/**
 * 通用行浏览：php tools/rows.php erp rnd_task_types 50
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$conn = $argv[1] ?? 'erp';
$table = $argv[2] ?? '';
$limit = (int) ($argv[3] ?? 100);
if (!$table) { echo "用法: php tools/rows.php <conn> <table> [limit]\n"; exit(1); }

$rows = DB::connection($conn)->table($table)->limit($limit)->get();
echo "[$conn.$table] " . count($rows) . " 行\n";
foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
