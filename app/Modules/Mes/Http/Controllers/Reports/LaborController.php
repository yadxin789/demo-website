<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\Reports;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 工时效率（M6-03）。
 *
 * 口径：效率 = 标准工时 / 实际工时。
 *   实际工时 = production_reports.minutes 合计
 *   标准工时 = Σ(该工序 done_qty × work_order_operations.std_minutes)
 * 按人 / 按工作中心 / 按班次三种切面，方便看板外单独复盘。
 */
class LaborController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = DateRange::resolve($request->query('from'), $request->query('to'), 13);
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $byUser = DB::connection('mes')
            ->table('production_reports as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->whereBetween('r.reported_at', [$start, $end])
            ->selectRaw('u.name as name, count(*) as reports, sum(r.ok_qty) as ok, sum(r.defect_qty) as ng, sum(r.minutes) as minutes')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('ok')
            ->get()
            ->map(function ($row) {
                $row->per_hour = $this->perHour($row->ok, $row->minutes);
                $row->yield = $this->yieldPct($row->ok, $row->ng);

                return $row;
            });

        $byCenter = DB::connection('mes')
            ->table('production_reports as r')
            ->join('work_order_operations as o', 'o.id', '=', 'r.operation_id')
            ->join('work_centers as c', 'c.id', '=', 'o.work_center_id')
            ->whereBetween('r.reported_at', [$start, $end])
            ->selectRaw('c.code as code, c.name as name, sum(r.ok_qty) as ok, sum(r.minutes) as minutes, sum((r.ok_qty + r.defect_qty) * o.std_minutes) as std_minutes')
            ->groupBy('c.id', 'c.code', 'c.name')
            ->orderByDesc('ok')
            ->get()
            ->map(function ($row) {
                $row->per_hour = $this->perHour($row->ok, $row->minutes);
                $row->efficiency = (float) $row->minutes > 0
                    ? round((float) $row->std_minutes / (float) $row->minutes * 100, 1)
                    : null;

                return $row;
            });

        // 标准工时按工序标准分钟 × 已报工数量（ok + 不良，都要占机时）
        $std = DB::connection('mes')
            ->table('production_reports as r')
            ->join('work_order_operations as o', 'o.id', '=', 'r.operation_id')
            ->whereBetween('r.reported_at', [$start, $end])
            ->selectRaw('sum((r.ok_qty + r.defect_qty) * o.std_minutes) as std_minutes, sum(r.minutes) as act_minutes')
            ->first();

        $byShift = DB::connection('mes')
            ->table('production_reports as r')
            ->join('shifts as s', 's.id', '=', 'r.shift_id')
            ->whereBetween('r.reported_at', [$start, $end])
            ->selectRaw('s.name as name, count(*) as reports, sum(r.ok_qty) as ok, sum(r.minutes) as minutes')
            ->groupBy('s.id', 's.name')
            ->orderBy('s.name')
            ->get()
            ->map(function ($row) {
                $row->per_hour = $this->perHour($row->ok, $row->minutes);

                return $row;
            });

        $stdMinutes = (float) ($std->std_minutes ?? 0);
        $actMinutes = (float) ($std->act_minutes ?? 0);
        $efficiency = $actMinutes > 0 ? round($stdMinutes / $actMinutes * 100, 1) : null;

        return view('mes::reports.labor', [
            'from' => $from,
            'to' => $to,
            'byUser' => $byUser,
            'byCenter' => $byCenter,
            'byShift' => $byShift,
            'stdMinutes' => $stdMinutes,
            'actMinutes' => $actMinutes,
            'efficiency' => $efficiency,
            'totalOk' => (float) $byUser->sum('ok'),
            'totalMinutes' => (int) $byUser->sum('minutes'),
        ]);
    }

    private function perHour($ok, $minutes): ?float
    {
        return (int) $minutes > 0 ? round((float) $ok / ((int) $minutes / 60), 1) : null;
    }

    private function yieldPct($ok, $ng): ?float
    {
        $total = (float) $ok + (float) $ng;

        return $total > 0 ? round((float) $ok / $total * 100, 1) : null;
    }
}
