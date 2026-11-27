<?php

declare(strict_types=1);

namespace App\Modules\Mes\Services;

use App\Modules\Mes\Models\ProductionReport;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Models\WorkOrderOperation;
use Illuminate\Support\Facades\DB;

/**
 * 报工服务（M3）。
 *
 * 口径说明（演示时要能讲清楚）：
 *   工序完成数 = 该工序历史报工的合格数累加；
 *   工单完成数 = 所有工序里最慢的那道（瓶颈决定产出）。不是简单相加，
 *   否则前道报 500、后道报 300 会得出 800 的假进度。
 *   工单不良数 = 各工序不良之和。
 */
class ReportService
{
    private const INT = 1.2;   // 允许 20% 超产

    /**
     * @param  array{ok_qty:float, defect_qty:float, minutes:int, shift_id?:?int, remark?:?string}  $data
     */
    public function create(WorkOrderOperation $operation, array $data, ?int $userId): ProductionReport
    {
        $ok = (float) $data['ok_qty'];
        $ng = (float) $data['defect_qty'];

        return DB::connection('mes')->transaction(function () use ($operation, $data, $userId, $ok, $ng) {
            $report = ProductionReport::query()->create([
                'work_order_id' => $operation->work_order_id,
                'operation_id' => $operation->id,
                'user_id' => $userId,
                'shift_id' => $data['shift_id'] ?? null,
                'ok_qty' => $ok,
                'defect_qty' => $ng,
                'minutes' => (int) ($data['minutes'] ?? 0),
                'reported_at' => now(),
                'remark' => $data['remark'] ?? null,
            ]);

            $operation->done_qty = (float) $operation->done_qty + $ok;
            $operation->defect_qty = (float) $operation->defect_qty + $ng;
            $operation->started_at ??= now();

            if (in_array($operation->status, ['pending', 'dispatched'], true)) {
                $operation->status = 'in_progress';
            }

            if ((float) $operation->done_qty >= (float) $operation->plan_qty) {
                $operation->status = 'done';
                $operation->finished_at ??= now();
            }

            $operation->save();

            $order = WorkOrder::query()->findOrFail($operation->work_order_id);
            $this->syncOrder($order);

            return $report;
        });
    }

    /** 按瓶颈工序回写工单数量与状态。 */
    public function syncOrder(WorkOrder $order): void
    {
        $ops = $order->operations()->get();
        if ($ops->isEmpty()) {
            return;
        }

        $minDone = (float) $ops->min(fn (WorkOrderOperation $op) => (float) $op->done_qty);
        $sumNg = (float) $ops->sum('defect_qty');

        $order->done_qty = $minDone;
        $order->defect_qty = $sumNg;

        if ($order->status === 'released') {
            $order->status = 'in_progress';
        }

        $plan = (float) $order->plan_qty;
        if ($plan > 0 && $minDone >= $plan) {
            $order->status = 'completed';
        } elseif ($order->status === 'completed' && $minDone < $plan) {
            // 返工场景：数量被改小或补报后回落，退回生产中
            $order->status = 'in_progress';
        }

        $order->save();
    }

    /** 报工前的上限校验：累计不得超计划的 120% */
    public function overflowReason(WorkOrderOperation $operation, float $ok, float $ng): ?string
    {
        $plan = (float) $operation->plan_qty;
        if ($plan <= 0) {
            return null;
        }

        $after = (float) $operation->done_qty + $ok + $ng;
        if ($after > $plan * self::INT) {
            return sprintf(
                '这道工序计划 %s 件，已报 %s，本次再报 %s 会超过计划的 120%%，请核对数量。',
                rtrim(rtrim(number_format($plan, 3), '0'), '.'),
                rtrim(rtrim(number_format((float) $operation->done_qty, 3), '0'), '.'),
                rtrim(rtrim(number_format($ok + $ng, 3), '0'), '.')
            );
        }

        return null;
    }
}
