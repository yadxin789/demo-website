<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\Reports;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Http\Controllers\Quality\DefectController;
use App\Modules\Mes\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 良率分析（M6-03）。
 *
 * 口径：日良率 = ok / (ok + defect)，与工作台 / 报工页 / 看板一致；
 * 不良 Top5 按 defects.defect_code 的数量汇总（qty 而非条数），
 * 检验通过率按 inspections.type 分组统计 pass / fail / 待检。
 */
class YieldController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = DateRange::resolve($request->query('from'), $request->query('to'), 13);
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $byDate = DB::connection('mes')->table('production_reports')
            ->whereBetween('reported_at', [$start, $end])
            ->selectRaw('date(reported_at) as d, sum(ok_qty) as ok, sum(defect_qty) as ng')
            ->groupByRaw('date(reported_at)')
            ->orderBy('d')
            ->get()
            ->map(function ($row) {
                $total = (float) $row->ok + (float) $row->ng;
                $row->yield = $total > 0 ? round((float) $row->ok / $total * 100, 1) : null;

                return $row;
            });

        $topDefects = DB::connection('mes')->table('defects')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('defect_code, count(*) as cases, sum(qty) as qty')
            ->groupBy('defect_code')
            ->orderByDesc('qty')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $row->label = DefectController::CODES[$row->defect_code] ?? $row->defect_code;

                return $row;
            });

        $inspectionRate = DB::connection('mes')->table('inspections')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("type, sum(case when result = 'pass' then 1 else 0 end) as pass, sum(case when result = 'fail' then 1 else 0 end) as fail, sum(case when result is null then 1 else 0 end) as pending")
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->map(function ($row) {
                $judged = (int) $row->pass + (int) $row->fail;
                $row->pct = $judged > 0 ? round((int) $row->pass / $judged * 100, 1) : null;

                return $row;
            });

        $totalOk = (float) $byDate->sum('ok');
        $totalNg = (float) $byDate->sum('ng');
        $overall = ($totalOk + $totalNg) > 0 ? round($totalOk / ($totalOk + $totalNg) * 100, 1) : 0.0;
        $best = $byDate->filter(fn ($r) => $r->yield !== null)->sortByDesc('yield')->first();
        $worst = $byDate->filter(fn ($r) => $r->yield !== null)->sortBy('yield')->first();

        // SVG 折线：把日期序列铺成 0~100 的纵坐标
        $points = $this->polyline($byDate);
        $maxDefectQty = max(1.0, (float) $topDefects->max('qty'));

        return view('mes::reports.yield', [
            'from' => $from,
            'to' => $to,
            'byDate' => $byDate,
            'topDefects' => $topDefects,
            'inspectionRate' => $inspectionRate,
            'totalOk' => $totalOk,
            'totalNg' => $totalNg,
            'overall' => $overall,
            'best' => $best,
            'worst' => $worst,
            'points' => $points,
            'maxDefectQty' => $maxDefectQty,
        ]);
    }

    /** 生成 SVG polyline 的 points 串（viewBox 0 0 1000 300，留 20 边距） */
    private function polyline($rows): string
    {
        $n = $rows->count();
        if ($n === 0) {
            return '';
        }

        $usable = max(1, $n - 1);
        $parts = [];
        foreach ($rows->values() as $i => $row) {
            if ($row->yield === null) {
                continue;
            }
            $x = 20 + ($i / $usable) * 960;
            $y = 280 - ((float) $row->yield / 100) * 260;
            $parts[] = sprintf('%.1f,%.1f', $x, $y);
        }

        return implode(' ', $parts);
    }
}
