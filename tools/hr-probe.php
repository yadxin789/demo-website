<?php

declare(strict_types=1);

/**
 * HR 验收小工具（承接 MES 的 probe 思路，避免 PowerShell 里内联 PHP 引号转义踩坑）。
 *
 * 用法：
 *   php tools/hr-probe.php sql "select ..."
 *   php tools/hr-probe.php cleanup-cell <code>      # 删掉测试部门（连带编制行）
 *   php tools/hr-probe.php counts                   # 各表计数速览
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = DB::connection('hr');
$cmd = $argv[1] ?? '';

if ($cmd === 'sql') {
    $sql = $argv[2] ?? '';
    if (! preg_match('/^\s*select\s/i', $sql)) {
        echo '只接受 select 语句';
        exit(1);
    }
    echo json_encode($db->select($sql), JSON_UNESCAPED_UNICODE);
    exit(0);
}

if ($cmd === 'counts') {
    foreach (['departments', 'positions', 'headcounts', 'employees', 'employee_contracts', 'attendance_records', 'leaves', 'payroll_runs', 'requisitions'] as $t) {
        printf("%-20s %d\n", $t, $db->table($t)->count());
    }
    exit(0);
}

if ($cmd === 'cleanup-cell') {
    $code = $argv[2] ?? 'D020';
    $ids = $db->table('departments')->where('code', $code)->pluck('id')->all();
    foreach ($ids as $id) {
        $db->table('headcounts')->where('department_id', $id)->delete();
        $db->table('departments')->where('id', $id)->delete();
    }
    printf("cleanup-cell: %s ids=%d | departments=%d headcounts=%d\n", $code, count($ids), $db->table('departments')->count(), $db->table('headcounts')->count());
    exit(0);
}

if ($cmd === 'cleanup-pos') {
    $code = $argv[2] ?? 'P036';
    $ids = $db->table('positions')->where('code', $code)->pluck('id')->all();
    foreach ($ids as $id) {
        $db->table('headcounts')->where('position_id', $id)->delete();
        $db->table('salary_structures')->where('position_id', $id)->delete();
        $db->table('positions')->where('id', $id)->delete();
    }
    printf("cleanup-pos: %s ids=%d | positions=%d\n", $code, count($ids), $db->table('positions')->count());
    exit(0);
}

if ($cmd === 'cleanup-emp') {
    $name = $argv[2] ?? 'TestHire';
    $ids = $db->table('employees')->where('name', $name)->pluck('id')->all();
    foreach ($ids as $id) {
        foreach (['employee_contracts', 'employee_movements', 'attendance_records', 'attendance_exceptions', 'overtimes', 'leaves', 'leave_balances', 'payroll_items', 'performance_reviews'] as $t) {
            if ($db->getSchemaBuilder()->hasTable($t)) {
                $db->table($t)->where('employee_id', $id)->delete();
            }
        }
        $db->table('employees')->where('id', $id)->delete();
    }
    printf("cleanup-emp: %s ids=%d | employees=%d contracts=%d movements=%d\n", $name, count($ids), $db->table('employees')->count(), $db->table('employee_contracts')->count(), $db->table('employee_movements')->count());
    exit(0);
}

if ($cmd === 'exec') {
    // 仅用于验收后复原测试数据（不是业务入口）
    $sql = $argv[2] ?? '';
    if (! preg_match('/^\s*(update|delete|insert)\s/i', $sql)) {
        echo '只接受 update / delete / insert';
        exit(1);
    }
    echo 'affected=' . $db->affectingStatement($sql);
    exit(0);
}

echo "unknown command: {$cmd}";
exit(1);
