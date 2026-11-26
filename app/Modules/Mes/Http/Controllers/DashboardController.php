<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\Defect;
use App\Modules\Mes\Models\Equipment;
use App\Modules\Mes\Models\ProductionReport;
use App\Modules\Mes\Models\WorkOrder;
use Illuminate\View\View;

/**
 * 车间工作台（M0 版：真实查询，空库也照常渲染）。
 *
 * KPI 口径：
 *   在制工单 = status in (released, in_progress)
 *   今日报工 = production_reports 今日 ok_qty 合计
 *   今日不良 = 今日 defect_qty 合计，良率 = ok / (ok + defect)
 *   设备异常 = equipments.status in (repairing, scrapped)
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->startOfDay();

        $inProgress = WorkOrder::query()->whereIn('status', ['released', 'in_progress'])->count();
        $todayOk = (float) ProductionReport::query()->where('reported_at', '>=', $today)->sum('ok_qty');
        $todayNg = (float) ProductionReport::query()->where('reported_at', '>=', $today)->sum('defect_qty');
        $yield = ($todayOk + $todayNg) > 0 ? round($todayOk / ($todayOk + $todayNg) * 100, 1) : 0.0;

        $openDefects = Defect::query()->where('status', 'open')->count();
        $equipIssues = Equipment::query()->whereIn('status', ['repairing', 'scrapped'])->count();

        $orders = WorkOrder::query()
            ->with('product')
            ->orderByRaw("case status when 'in_progress' then 0 when 'released' then 1 when 'draft' then 2 else 3 end")
            ->orderBy('plan_end')
            ->limit(8)
            ->get();

        return view('mes::dashboard', [
            'inProgress' => $inProgress,
            'todayOk' => $todayOk,
            'yield' => $yield,
            'openDefects' => $openDefects,
            'equipIssues' => $equipIssues,
            'orders' => $orders,
        ]);
    }
}
