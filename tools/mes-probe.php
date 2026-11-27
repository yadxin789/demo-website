<?php

declare(strict_types=1);

/**
 * MES 验收小工具（避免 PowerShell 里内联 PHP 引号转义踩坑）。
 *
 * 用法：
 *   php tools/mes-probe.php count <html文件> <子串>
 *   php tools/mes-probe.php line <bomId>
 *   php tools/mes-probe.php line-value <lineId>
 *   php tools/mes-probe.php cleanup
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = DB::connection('mes');
$cmd = $argv[1] ?? '';

if ($cmd === 'count') {
    $file = $argv[2];
    $needle = $argv[3];
    if (! is_file($file)) {
        echo 'NOFILE';
        exit(1);
    }
    echo mb_substr_count(file_get_contents($file), $needle);
    exit(0);
}

if ($cmd === 'line') {
    $bomId = (int) ($argv[2] ?? 0);
    $line = $db->table('bom_lines')->where('bom_id', $bomId)->orderBy('id')->first();
    echo $line ? "{$line->id}|{$line->qty_per}|{$line->loss_rate}|{$line->seq}" : 'NONE';
    exit(0);
}

if ($cmd === 'line-value') {
    $id = (int) ($argv[2] ?? 0);
    $line = $db->table('bom_lines')->where('id', $id)->first();
    echo $line ? "{$line->qty_per}|{$line->loss_rate}|{$line->seq}" : 'NONE';
    exit(0);
}

if ($cmd === 'sql') {
    // 只允许简单 select，避免误写
    $sql = $argv[2] ?? '';
    if (! preg_match('/^\s*select\s/i', $sql)) {
        echo '只接受 select 语句';
        exit(1);
    }
    echo json_encode(DB::connection('mes')->select($sql), JSON_UNESCAPED_UNICODE);
    exit(0);
}

if ($cmd === 'cleanup-import') {
    $codes = ['MJ-RM-901', 'MJ-FG-901', 'MJ-RM-902'];
    $mats = $db->table('materials')->whereIn('code', $codes)->pluck('id')->all();

    $bomIds = $db->table('boms')->where('code', 'like', 'BOM-FG-9%')->pluck('id')->all();
    foreach ($bomIds as $id) {
        $db->table('bom_lines')->where('bom_id', $id)->delete();
        $db->table('boms')->where('id', $id)->delete();
    }

    $woIds = $db->table('work_orders')->where('order_no', 'like', 'WO-2026-9%')->pluck('id')->all();
    foreach ($woIds as $id) {
        $db->table('work_order_operations')->where('work_order_id', $id)->delete();
        $db->table('production_reports')->where('work_order_id', $id)->delete();
        $db->table('work_orders')->where('id', $id)->delete();
    }

    if ($mats !== []) {
        $db->table('materials')->whereIn('id', $mats)->delete();
    }

    printf(
        "cleanup-import: materials=%d boms=%d work_orders=%d\n",
        count($mats),
        count($bomIds),
        count($woIds)
    );
    printf(
        "  现有：materials=%d boms=%d bom_lines=%d work_orders=%d\n",
        $db->table('materials')->count(),
        $db->table('boms')->count(),
        $db->table('bom_lines')->count(),
        $db->table('work_orders')->count()
    );
    exit(0);
}

if ($cmd === 'cleanup') {
    $boms = $db->table('boms')->where('code', 'like', 'BOM-FG-09%')->pluck('id')->all();
    foreach ($boms as $id) {
        $db->table('bom_lines')->where('bom_id', $id)->delete();
        $db->table('boms')->where('id', $id)->delete();
    }
    $rts = $db->table('routings')->where('code', 'like', 'RT-FG-09%')->pluck('id')->all();
    foreach ($rts as $id) {
        $db->table('routing_operations')->where('routing_id', $id)->delete();
        $db->table('routings')->where('id', $id)->delete();
    }
    printf(
        "cleanup: boms=%d routings=%d | 总量 boms=%d bom_lines=%d routings=%d routing_operations=%d\n",
        count($boms),
        count($rts),
        $db->table('boms')->count(),
        $db->table('bom_lines')->count(),
        $db->table('routings')->count(),
        $db->table('routing_operations')->count()
    );
    exit(0);
}

echo "unknown command: {$cmd}";
exit(1);
