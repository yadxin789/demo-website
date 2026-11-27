<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\WorkOrderOperation;
use App\Modules\Mes\Services\ApsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 生产排产（M2-08 / M2-09）。
 *
 * 甘特图：工作中心（行）× 未来 5 天（列），色块按时长占位；
 * 一键排产走 ApsService::rebuild，可反复点，结果幂等（先清未来再重排）。
 */
class ApsController extends Controller
{
    public function index(ApsService $aps): View
    {
        [$grid, $from, $centers, $load] = $aps->grid(5);

        // 待排工序（同 ApsService 口径）：已下达、还没开工的工序
        $pending = WorkOrderOperation::query()
            ->whereIn('status', ['pending', 'dispatched'])
            ->whereNull('started_at')
            ->whereHas('workOrder', fn ($q) => $q->where('status', 'released'))
            ->get(['id', 'plan_qty', 'done_qty', 'std_minutes']);

        $pendingMinutes = (int) round($pending->sum(
            fn ($op) => max(0.0, (float) $op->plan_qty - (float) $op->done_qty) * max(0.2, (float) $op->std_minutes)
        ));

        $scheduledMinutes = (int) array_sum(array_map(fn ($l) => $l['minutes'], $load));
        $capacityMinutes = $centers->count() * 5 * ApsService::DAY_SPAN;

        return view('mes::aps.index', [
            'grid' => $grid,
            'from' => $from,
            'days' => 5,
            'centers' => $centers,
            'load' => $load,
            'pendingOps' => $pending->count(),
            'pendingMinutes' => $pendingMinutes,
            'scheduledMinutes' => $scheduledMinutes,
            'capacityMinutes' => $capacityMinutes,
            'overflowMinutes' => max(0, $pendingMinutes - $scheduledMinutes),
            'blocks' => array_sum(array_map(fn ($l) => $l['blocks'], $load)),
            'overflowBlocks' => array_sum(array_map(fn ($l) => $l['overflow'], $load)),
        ]);
    }

    public function schedule(Request $request, ApsService $aps): RedirectResponse
    {
        $r = $aps->rebuild(5);

        $msg = "已按待排工序重排未来 5 天：{$r['ops']} 道工序占用 {$r['minutes']} 分钟，生成 {$r['blocks']} 个作业块。";
        if ($r['overflow_minutes'] > 0) {
            $msg .= " 窗口外还有 {$r['overflow_minutes']} 分钟（约 " . round($r['overflow_minutes'] / 720, 1) . " 个班次）排不进，需加班 / 外协 / 顺延。";
        }

        return redirect()->route('mes.aps.index')->with('status', $msg);
    }
}
