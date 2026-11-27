<?php

declare(strict_types=1);

namespace App\Modules\Mes\Services;

use App\Modules\Mes\Models\ApsSchedule;
use App\Modules\Mes\Models\WorkCenter;
use App\Modules\Mes\Models\WorkOrderOperation;
use Illuminate\Support\Carbon;

/**
 * 简版排产（M2-07 / M2-09）。
 *
 * 明确不做真 APS 算法（规划书 §0.3）：这里是「按工作中心日产能的贪心顺排切块」——
 *   1. 取已下达/生产工单里未完成的工序，按 交期 → 优先级 → 工序号 排序；
 *   2. 每道工序的待做工时 = (计划数 - 已完成数) × 工序标准分钟；
 *   3. 逐个工作中心往后填，一天填满（08:00–20:00）就顺延次日，最多排到第 $days 天；
 *   4. 窗口内排不下的工序计入 skipped，页面上明确告诉客户"产能不足，需加班/外协/顺延"。
 *
 * 口径：日产能取 work_centers.daily_capacity（单位=分钟）。这是让客户看"排得下排不下"的示意，
 * 不是可执行排程；真实排产还要设备日历、换型时间、工装约束。
 */
class ApsService
{
    public const DAY_START_MIN = 8 * 60;    // 08:00
    public const DAY_END_MIN = 20 * 60;     // 20:00
    public const DAY_SPAN = self::DAY_END_MIN - self::DAY_START_MIN;

    /**
     * 重建未来 $days 天的排产。
     *
     * @return array{blocks: int, ops: int, skipped: int, minutes: int, overflow_minutes: int}
     */
    public function rebuild(int $days = 5): array
    {
        $today = now()->startOfDay();

        // 只清"今天及以后"的排产，历史留档不动
        ApsSchedule::query()->where('start_at', '>=', $today)->delete();

        $centers = WorkCenter::query()->where('status', 'active')->get();
        if ($centers->isEmpty()) {
            return ['blocks' => 0, 'ops' => 0, 'skipped' => 0, 'minutes' => 0, 'overflow_minutes' => 0];
        }

        // 每个中心的游标：第几天、当天已占用分钟、当天第几块
        $state = [];
        foreach ($centers as $c) {
            $state[$c->id] = ['day' => 0, 'used' => 0, 'seq' => 0];
        }

        // 只排「已下达、还没开工」的工序：草稿工单不排，已开工的在产线上跑、不重复排
        $ops = WorkOrderOperation::query()
            ->whereIn('status', ['pending', 'dispatched'])
            ->whereNull('started_at')
            ->whereHas('workOrder', fn ($q) => $q->where('status', 'released'))
            ->with('workOrder:id,order_no,status,plan_end,priority')
            ->get()
            ->sortBy(fn ($op) => sprintf(
                '%s|%02d|%04d',
                $op->workOrder?->plan_end ?? '9999-12-31',
                (int) ($op->workOrder?->priority ?? 5),
                (int) $op->op_no
            ))
            ->values();

        $blocks = 0;
        $skipped = 0;
        $minutes = 0;
        $overflowMinutes = 0;

        foreach ($ops as $op) {
            $center = $centers->firstWhere('id', $op->work_center_id);
            if (! $center) {
                continue;
            }

            $cap = max(60, (int) $center->daily_capacity);
            $remainQty = max(0.0, (float) $op->plan_qty - (float) $op->done_qty);
            $need = (int) round($remainQty * max(0.2, (float) $op->std_minutes));

            if ($need <= 0) {
                continue;
            }

            while ($need > 0) {
                $s = &$state[$center->id];

                if ($s['day'] > $days - 1) {
                    $skipped++;          // 5 天窗口挤不下，留给下一轮或加班
                    $overflowMinutes += $need;
                    break;
                }

                $avail = $cap - $s['used'];
                if ($avail <= 0) {
                    $s['day']++;
                    $s['used'] = 0;
                    $s['seq'] = 0;
                    continue;
                }

                $take = min($avail, $need);
                $startMin = self::DAY_START_MIN + $s['used'];
                $start = $today->copy()->addDays($s['day'])
                    ->setTime(intdiv($startMin, 60), $startMin % 60);
                $end = $start->copy()->addMinutes($take);

                ApsSchedule::query()->create([
                    'work_order_id' => $op->work_order_id,
                    'operation_id' => $op->id,
                    'work_center_id' => $center->id,
                    'start_at' => $start,
                    'end_at' => $end,
                    'seq' => $s['seq'] + 1,
                ]);

                $s['used'] += $take;
                $s['seq']++;
                $need -= $take;
                $blocks++;
                $minutes += $take;
            }
            unset($s);
        }

        return ['blocks' => $blocks, 'ops' => $ops->count(), 'skipped' => $skipped, 'minutes' => $minutes, 'overflow_minutes' => $overflowMinutes];
    }

    /**
     * 取 $days 天的甘特网格：$grid[工作中心 id][第 n 天] = 色块集合（每块带 left/width 百分比）
     *
     * @return array{0: array<int, array<int, array<int, array<string, mixed>>>>, 1: Carbon, 2: \Illuminate\Support\Collection, 3: array<int, array<string, mixed>>}
     */
    public function grid(int $days = 5): array
    {
        $today = now()->startOfDay();
        $end = $today->copy()->addDays($days - 1)->endOfDay();

        $centers = WorkCenter::query()->where('status', 'active')->orderBy('code')->get();

        $rows = ApsSchedule::query()
            ->with([
                'workOrder:id,order_no,status,plan_end,product_material_id',
                'workOrder.product:id,code,name',
                'operation:id,op_no,op_name,plan_qty,done_qty,status',
            ])
            ->whereBetween('start_at', [$today, $end])
            ->orderBy('start_at')
            ->orderBy('seq')
            ->get();

        $grid = [];
        $load = [];
        foreach ($centers as $c) {
            $grid[$c->id] = array_fill(0, $days, []);
            $load[$c->id] = ['blocks' => 0, 'minutes' => 0, 'overflow' => 0];
        }

        foreach ($rows as $b) {
            $dayIndex = (int) floor($today->diffInDays($b->start_at->copy()->startOfDay(), false));
            if ($dayIndex < 0 || $dayIndex >= $days || ! isset($grid[$b->work_center_id])) {
                continue;
            }

            $startMin = $b->start_at->hour * 60 + $b->start_at->minute;
            $duration = max(1, (int) $b->start_at->diffInMinutes($b->end_at));

            $leftRaw = max(0, $startMin - self::DAY_START_MIN);
            $left = min(100, $leftRaw / self::DAY_SPAN * 100);
            $width = min(100 - $left, $duration / self::DAY_SPAN * 100);
            $overflow = ($startMin + $duration) > self::DAY_END_MIN;

            $grid[$b->work_center_id][$dayIndex][] = [
                'id' => $b->id,
                'work_order_id' => $b->work_order_id,
                'order_no' => $b->workOrder?->order_no ?? '',
                'order_status' => $b->workOrder?->status ?? 'released',
                'op_no' => $b->operation?->op_no,
                'op_name' => $b->operation?->op_name ?? '',
                'qty' => (float) ($b->operation?->plan_qty ?? 0),
                'done' => (float) ($b->operation?->done_qty ?? 0),
                'start' => $b->start_at->format('H:i'),
                'end' => $b->end_at->format('H:i'),
                'left' => round($left, 2),
                'width' => round(max(3, $width), 2),
                'overflow' => $overflow,
            ];

            $load[$b->work_center_id]['blocks']++;
            $load[$b->work_center_id]['minutes'] += $duration;
            if ($overflow) {
                $load[$b->work_center_id]['overflow']++;
            }
        }

        foreach ($load as $cid => $l) {
            $load[$cid]['pct'] = round($l['minutes'] / ($days * self::DAY_SPAN) * 100, 1);
        }

        return [$grid, $today, $centers, $load];
    }
}
