<?php

/**
 * 把 ERP 系统管理员（username=admin）追加为各审批流节点的代批人，
 * 让演示账号登录后「审批中心」不为空。幂等：
 *   php tools/patch-approval-delegates.php
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$adminId = (int) (DB::connection('erp')->table('users')->where('username', 'admin')->value('id') ?? 0);
if ($adminId === 0) {
    echo "未找到 username=admin 的账号，跳过\n";
    exit(1);
}

foreach (DB::connection('erp')->table('approval_flows')->get() as $f) {
    $steps = json_decode($f->steps_json, true) ?: [];
    $changed = false;
    foreach ($steps as &$s) {
        $d = array_map('intval', $s['delegate_ids'] ?? []);
        if (! in_array($adminId, $d, true)) {
            $d[] = $adminId;
            $s['delegate_ids'] = $d;
            $changed = true;
        }
    }
    unset($s);
    if ($changed) {
        DB::connection('erp')->table('approval_flows')->where('id', $f->id)
            ->update(['steps_json' => json_encode($steps, JSON_UNESCAPED_UNICODE)]);
        echo "flow {$f->id} {$f->flow_code} 已追加代批人 {$adminId}\n";
    }
}
echo "完成\n";
