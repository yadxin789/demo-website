<?php

declare(strict_types=1);

/**
 * 修正 MES 演示数据的工序状态（一次性脚本，可重复执行）。
 *
 * 背景：早期 Seeder 把"只要报过一点"的工序都标成 done，导致工单还在生产中，
 * 工序列表却全绿、看不出卡在哪一道。这里按数量重算：
 *   done_qty >= plan_qty  → done（有 finished_at）
 *   0 < done_qty < plan   → in_progress（无 finished_at）
 *   其余保持原状（pending / dispatched）
 * 同时按瓶颈口径回写工单 done_qty 与状态。
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = DB::connection('mes');

$orders = $db->table('work_orders')->get();
$touched = 0;

foreach ($orders as $order) {
    $ops = $db->table('work_order_operations')->where('work_order_id', $order->id)->get();
    if ($ops->isEmpty()) {
        continue;
    }

    foreach ($ops as $op) {
        $done = (float) $op->done_qty;
        $plan = (float) $op->plan_qty;

        if ($done >= $plan && $op->status !== 'done') {
            $db->table('work_order_operations')->where('id', $op->id)->update([
                'status' => 'done',
                'finished_at' => $op->finished_at ?: now()->subHours(2),
                'updated_at' => now(),
            ]);
            $touched++;
        } elseif ($done > 0 && $done < $plan && in_array($op->status, ['done', 'pending', 'dispatched'], true)) {
            $db->table('work_order_operations')->where('id', $op->id)->update([
                'status' => 'in_progress',
                'started_at' => $op->started_at ?: now()->subDays(1),
                'finished_at' => null,
                'updated_at' => now(),
            ]);
            $touched++;
        } elseif ($done == 0 && $op->status === 'done') {
            $db->table('work_order_operations')->where('id', $op->id)->update([
                'status' => 'pending',
                'finished_at' => null,
                'updated_at' => now(),
            ]);
            $touched++;
        }
    }

    // 按瓶颈回写工单
    $minDone = (float) $db->table('work_order_operations')->where('work_order_id', $order->id)->min('done_qty');
    $sumNg = (float) $db->table('work_order_operations')->where('work_order_id', $order->id)->sum('defect_qty');
    $planQty = (float) $order->plan_qty;

    $newStatus = $order->status;
    if (! in_array($order->status, ['draft', 'closed'], true)) {
        $newStatus = ($minDone >= $planQty && $planQty > 0) ? 'completed' : 'in_progress';
    }

    $db->table('work_orders')->where('id', $order->id)->update([
        'done_qty' => $minDone,
        'defect_qty' => $sumNg,
        'status' => $newStatus,
        'updated_at' => now(),
    ]);
}

// 汇总
$byStatus = $db->table('work_order_operations')
    ->select('status', DB::raw('count(*) as n'))
    ->groupBy('status')
    ->pluck('n', 'status');

$woByStatus = $db->table('work_orders')
    ->select('status', DB::raw('count(*) as n'))
    ->groupBy('status')
    ->pluck('n', 'status');

echo "修正工序 {$touched} 条\n";
echo '工序状态：' . json_encode($byStatus, JSON_UNESCAPED_UNICODE) . "\n";
echo '工单状态：' . json_encode($woByStatus, JSON_UNESCAPED_UNICODE) . "\n";
