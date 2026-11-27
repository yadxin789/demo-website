<?php

namespace App\Modules\Mes\Services;

use App\Modules\Mes\Models\Defect;
use App\Modules\Mes\Models\Equipment;
use App\Modules\Mes\Models\EquipmentRepair;
use App\Modules\Mes\Models\Inspection;
use App\Modules\Mes\Models\MaterialIssue;
use App\Modules\Mes\Models\ProductionReport;
use App\Modules\Mes\Models\WorkCenter;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Models\WorkOrderOperation;

/**
 * 车间看板取数（M6-01）。
 *
 * 口径与工作台 / 报工页保持一致：
 *   今日产出 = 今日 production_reports.ok_qty 合计
 *   今日良率 = ok / (ok + defect)，无报工时取近 7 天
 *   在制     = work_orders.status in (released, in_progress)
 *   在制待完成 = Σ(plan_qty - done_qty)，done_qty 已是瓶颈口径
 */
class KanbanService
{
    public function snapshot(): array
    {
        $now = now();
        $today = $now->toDateString();

        $okToday = (float) ProductionReport::query()->whereDate('reported_at', $today)->sum('ok_qty');
        $ngToday = (float) ProductionReport::query()->whereDate('reported_at', $today)->sum('defect_qty');
        $reportsToday = ProductionReport::query()->whereDate('reported_at', $today)->count();
        $minutesToday = (int) ProductionReport::query()->whereDate('reported_at', $today)->sum('minutes');

        $yieldToday = ($okToday + $ngToday) > 0
            ? round($okToday / ($okToday + $ngToday) * 100, 1)
            : $this->recentYield();

        $wip = WorkOrder::query()->whereIn('status', ['released', 'in_progress'])->get(['id', 'plan_qty', 'done_qty']);
        $wipQty = (float) $wip->sum('plan_qty');
        $wipRemain = max(0, (float) $wip->sum(fn ($o) => (float) $o->plan_qty - (float) $o->done_qty));

        $equipRunning = Equipment::query()->where('status', 'running')->count();
        $equipTotal = Equipment::query()->count();

        return [
            'generated_at' => $now,
            'today' => $today,
            'ok_today' => $okToday,
            'ng_today' => $ngToday,
            'yield_today' => $yieldToday,
            'yield_source' => ($okToday + $ngToday) > 0 ? 'today' : 'recent',
            'reports_today' => $reportsToday,
            'minutes_today' => $minutesToday,
            'wip_orders' => $wip->count(),
            'wip_qty' => $wipQty,
            'wip_remain' => $wipRemain,
            'equip_running' => $equipRunning,
            'equip_total' => $equipTotal,
            'centers' => $this->centers(),
            'feed' => $this->feed(),
            'alerts' => $this->alerts(),
        ];
    }

    /** 近 7 天良率（今日无报工时兜底展示） */
    private function recentYield(): float
    {
        $ok = (float) ProductionReport::query()->where('reported_at', '>=', now()->subDays(7))->sum('ok_qty');
        $ng = (float) ProductionReport::query()->where('reported_at', '>=', now()->subDays(7))->sum('defect_qty');

        return ($ok + $ng) > 0 ? round($ok / ($ok + $ng) * 100, 1) : 0.0;
    }

    /** 各工作中心在制工序负荷 */
    private function centers(): array
    {
        $ops = WorkOrderOperation::query()
            ->whereHas('workOrder', fn ($q) => $q->whereIn('status', ['released', 'in_progress']))
            ->get(['work_center_id', 'status', 'plan_qty', 'done_qty']);

        return WorkCenter::query()->orderBy('code')->get(['id', 'code', 'name'])
            ->map(function ($c) use ($ops) {
                $mine = $ops->where('work_center_id', $c->id);
                $plan = (float) $mine->sum('plan_qty');
                $done = (float) $mine->sum('done_qty');
                $active = $mine->whereIn('status', ['dispatched', 'in_progress'])->count();

                return [
                    'code' => $c->code,
                    'name' => $c->name,
                    'ops' => $mine->count(),
                    'active' => $active,
                    'done_ops' => $mine->where('status', 'done')->count(),
                    'qty_plan' => $plan,
                    'qty_done' => $done,
                    'pct' => $plan > 0 ? min(100, round($done / $plan * 100)) : 0,
                ];
            })
            ->all();
    }

    /** 最近报工流水（看板右下滚动） */
    private function feed(): array
    {
        return ProductionReport::query()
            ->with(['user:id,name', 'operation:id,op_name', 'workOrder:id,order_no'])
            ->orderByDesc('reported_at')
            ->limit(9)
            ->get()
            ->map(fn ($r) => [
                'time' => $r->reported_at?->format('H:i') ?? '',
                'user' => $r->user?->name ?? '—',
                'order' => $r->workOrder?->order_no ?? '',
                'op' => $r->operation?->op_name ?? '',
                'ok' => (float) $r->ok_qty,
                'ng' => (float) $r->defect_qty,
            ])
            ->all();
    }

    /** 异常清单：缺料 / 卡检 / 停机 / 逾期 */
    private function alerts(): array
    {
        $list = [];

        MaterialIssue::query()
            ->with('workOrder:id,order_no')
            ->whereIn('status', ['draft', 'partial'])
            ->orderBy('id')
            ->limit(4)
            ->get()
            ->each(function ($mi) use (&$list) {
                $list[] = [
                    'type' => '缺料',
                    'tone' => 'warn',
                    'text' => ($mi->workOrder?->order_no ?? $mi->issue_no).' 领料未发齐',
                ];
            });

        $pending = Inspection::query()->whereNull('result')->count();
        if ($pending > 0) {
            $list[] = ['type' => '待检', 'tone' => 'warn', 'text' => $pending.' 张检验单待判定'];
        }

        EquipmentRepair::query()
            ->with('equipment:id,code,name')
            ->whereIn('status', ['open', 'handling'])
            ->limit(3)
            ->get()
            ->each(function ($rp) use (&$list) {
                $list[] = [
                    'type' => '停机',
                    'tone' => 'bad',
                    'text' => ($rp->equipment?->code ?? '设备').' '.mb_substr((string) $rp->fault_desc, 0, 14),
                ];
            });

        $openDefects = Defect::query()->where('status', 'open')->count();
        if ($openDefects > 0) {
            $list[] = ['type' => '不良', 'tone' => 'warn', 'text' => $openDefects.' 条不良待处置'];
        }

        $late = WorkOrder::query()
            ->whereIn('status', ['released', 'in_progress'])
            ->whereNotNull('plan_end')
            ->whereDate('plan_end', '<', now()->toDateString())
            ->count();
        if ($late > 0) {
            $list[] = ['type' => '逾期', 'tone' => 'bad', 'text' => $late.' 张工单已过计划完工日'];
        }

        return array_slice($list, 0, 8);
    }
}
