<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\ProductionReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 报工流水（M3）：所有工序报工的明细账，按日期与工单查。
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $q = trim((string) $request->query('q', ''));

        $reports = ProductionReport::query()
            ->with([
                'operation:id,op_no,op_name',
                'workOrder:id,order_no,product_material_id',
                'workOrder.product:id,code,name',
                'user:id,name',
                'shift:id,code,name',
            ])
            ->when($from !== '', fn ($query) => $query->whereDate('reported_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('reported_at', '<=', $to))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->whereHas('workOrder', fn ($q2) => $q2->where('order_no', 'like', "%{$q}%"))
                        ->orWhereHas('operation', fn ($q2) => $q2->where('op_name', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $today = now()->startOfDay();
        $todayOk = (float) ProductionReport::query()->where('reported_at', '>=', $today)->sum('ok_qty');
        $todayNg = (float) ProductionReport::query()->where('reported_at', '>=', $today)->sum('defect_qty');
        $yield = ($todayOk + $todayNg) > 0 ? round($todayOk / ($todayOk + $todayNg) * 100, 1) : 0.0;

        return view('mes::reports.index', [
            'reports' => $reports,
            'from' => $from,
            'to' => $to,
            'q' => $q,
            'stat' => [
                'today_count' => ProductionReport::query()->where('reported_at', '>=', $today)->count(),
                'today_ok' => $todayOk,
                'today_ng' => $todayNg,
                'yield' => $yield,
                'all_count' => ProductionReport::query()->count(),
                'all_minutes' => (int) ProductionReport::query()->sum('minutes'),
            ],
        ]);
    }
}
