<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\Reports;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 产量趋势（M6-03）。
 *
 * 口径：产量 = production_reports.ok_qty 合计（合格量），不良不冲减、单列展示。
 * 默认近 14 天，可改日期区间。
 */
class OutputController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = DateRange::resolve($request->query('from'), $request->query('to'), 13);
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $byDate = DB::connection('mes')->table('production_reports')
            ->whereBetween('reported_at', [$start, $end])
            ->selectRaw('date(reported_at) as d, sum(ok_qty) as ok, sum(defect_qty) as ng, count(*) as reports')
            ->groupByRaw('date(reported_at)')
            ->orderBy('d')
            ->get();

        $byProduct = DB::connection('mes')
            ->table('production_reports as r')
            ->join('work_orders as w', 'w.id', '=', 'r.work_order_id')
            ->join('materials as m', 'm.id', '=', 'w.product_material_id')
            ->whereBetween('r.reported_at', [$start, $end])
            ->selectRaw('m.code as code, m.name as name, sum(r.ok_qty) as ok, sum(r.defect_qty) as ng')
            ->groupBy('m.id', 'm.code', 'm.name')
            ->orderByDesc('ok')
            ->get();

        $byOrder = DB::connection('mes')
            ->table('production_reports as r')
            ->join('work_orders as w', 'w.id', '=', 'r.work_order_id')
            ->whereBetween('r.reported_at', [$start, $end])
            ->selectRaw('w.order_no as order_no, w.plan_qty as plan_qty, w.status as status, sum(r.ok_qty) as ok, sum(r.defect_qty) as ng')
            ->groupBy('w.id', 'w.order_no', 'w.plan_qty', 'w.status')
            ->orderByDesc('ok')
            ->limit(10)
            ->get();

        $totalOk = (float) $byDate->sum('ok');
        $totalNg = (float) $byDate->sum('ng');
        $peak = $byDate->sortByDesc('ok')->first();
        $days = max(1, (int) $from->diffInDays($to) + 1);

        return view('mes::reports.output', [
            'from' => $from,
            'to' => $to,
            'byDate' => $byDate,
            'byProduct' => $byProduct,
            'byOrder' => $byOrder,
            'maxOk' => max(1.0, (float) $byDate->max('ok')),
            'totalOk' => $totalOk,
            'totalNg' => $totalNg,
            'days' => $days,
            'avgOk' => $totalOk / $days,
            'peak' => $peak,
        ]);
    }
}
