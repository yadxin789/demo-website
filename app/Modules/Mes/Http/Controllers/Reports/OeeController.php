<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\Reports;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\ProductionReport;
use App\Modules\Mes\Models\WorkCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * OEE 报表（MES，M7 增补）。
 *
 * 口径对标 Odoo / ERPNext 的设备综合效率：
 *   可用率 A = 运行时间 ÷ (运行时间 + 停机时间)
 *              运行时间取报工 minutes，停机时间取设备维修单 down_minutes
 *   性能 P = 理论时间 ÷ 运行时间，理论时间 = Σ(合格数 × 单件标准工时)
 *   良率 Q = 合格数 ÷ (合格数 + 不良数)
 *   OEE = A × P × Q
 * 维度按工作中心聚合（报工与设备都挂在 work_center 上，能对齐）。
 */
class OeeController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->filled('from')
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : now()->subDays(6)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : now()->endOfDay();

        // 报工维度：工作中心 => 运行分钟 / 合格 / 不良 / 理论分钟
        $reports = ProductionReport::query()
            ->join('work_order_operations as ops', 'ops.id', '=', 'production_reports.operation_id')
            ->whereBetween('production_reports.reported_at', [$from, $to])
            ->groupBy('ops.work_center_id')
            ->selectRaw('ops.work_center_id as wc_id')
            ->selectRaw('SUM(production_reports.minutes) as run_minutes')
            ->selectRaw('SUM(production_reports.ok_qty) as ok_qty')
            ->selectRaw('SUM(production_reports.defect_qty) as ng_qty')
            // 理论分钟 = 合格数 × 单件标准工时（std_minutes / plan_qty）
            ->selectRaw('SUM(CASE WHEN ops.plan_qty > 0 THEN production_reports.ok_qty * (ops.std_minutes / ops.plan_qty) ELSE 0 END) as ideal_minutes')
            ->get()
            ->keyBy('wc_id');

        // 停机维度：工作中心 => 停机分钟
        $down = DB::connection('mes')
            ->table('equipment_repairs')
            ->join('equipments', 'equipments.id', '=', 'equipment_repairs.equipment_id')
            ->whereBetween('equipment_repairs.created_at', [$from, $to])
            ->groupBy('equipments.work_center_id')
            ->selectRaw('equipments.work_center_id as wc_id, SUM(equipment_repairs.down_minutes) as down_minutes')
            ->pluck('down_minutes', 'wc_id');

        $centers = WorkCenter::query()->orderBy('code')->get(['id', 'code', 'name']);

        $rows = [];
        foreach ($centers as $c) {
            $r = $reports[$c->id] ?? null;
            $run = (float) ($r->run_minutes ?? 0);
            $downMin = (float) ($down[$c->id] ?? 0);
            $ok = (float) ($r->ok_qty ?? 0);
            $ng = (float) ($r->ng_qty ?? 0);
            $ideal = (float) ($r->ideal_minutes ?? 0);

            $a = ($run + $downMin) > 0 ? $run / ($run + $downMin) : 0.0;
            $p = $run > 0 ? min(1.0, $ideal / $run) : 0.0;
            $q = ($ok + $ng) > 0 ? $ok / ($ok + $ng) : 0.0;

            $rows[] = [
                'code' => $c->code,
                'name' => $c->name,
                'run' => $run,
                'down' => $downMin,
                'ok' => $ok,
                'ng' => $ng,
                'ideal' => $ideal,
                'a' => $a,
                'p' => $p,
                'q' => $q,
                'oee' => $a * $p * $q,
                'active' => $run > 0 || $downMin > 0,
            ];
        }

        $totalRun = array_sum(array_column($rows, 'run'));
        $totalDown = array_sum(array_column($rows, 'down'));
        $totalOk = array_sum(array_column($rows, 'ok'));
        $totalNg = array_sum(array_column($rows, 'ng'));
        $totalIdeal = array_sum(array_column($rows, 'ideal'));

        $allA = ($totalRun + $totalDown) > 0 ? $totalRun / ($totalRun + $totalDown) : 0.0;
        $allP = $totalRun > 0 ? min(1.0, $totalIdeal / $totalRun) : 0.0;
        $allQ = ($totalOk + $totalNg) > 0 ? $totalOk / ($totalOk + $totalNg) : 0.0;

        // 按日趋势
        $daily = ProductionReport::query()
            ->whereBetween('reported_at', [$from, $to])
            ->groupByRaw('DATE(reported_at)')
            ->selectRaw('DATE(reported_at) as d, SUM(minutes) as run_minutes, SUM(ok_qty) as ok_qty, SUM(defect_qty) as ng_qty')
            ->orderBy('d')
            ->get()
            ->map(function ($row) use ($down) {
                $ok = (float) $row->ok_qty;
                $ng = (float) $row->ng_qty;

                return [
                    'd' => $row->d,
                    'run' => (float) $row->run_minutes,
                    'ok' => $ok,
                    'ng' => $ng,
                    'q' => ($ok + $ng) > 0 ? $ok / ($ok + $ng) : 0,
                ];
            });

        return view('mes::reports.oee', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'all' => ['a' => $allA, 'p' => $allP, 'q' => $allQ, 'oee' => $allA * $allP * $allQ],
            'daily' => $daily,
            'totalRun' => $totalRun,
            'totalDown' => $totalDown,
            'totalOk' => $totalOk,
            'totalNg' => $totalNg,
        ]);
    }
}
