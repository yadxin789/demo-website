<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\ProductionReport;
use App\Modules\Mes\Models\Shift;
use App\Modules\Mes\Models\WorkOrderOperation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 我的任务（M3-07）：操作工视角。
 *
 * 只列已下达、未完成的工序：
 *   - 普通账号 → 只看派给自己的
 *   - admin 账号 → 看全车间（演示时只用一个 admin 账号，否则页面是空的），页头会注明视角
 * 点「去报工」进独立的移动优先报工页，提交后回到本页。
 */
class MyTaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth('mes')->user();
        $isAdmin = $user->hasRole('admin');
        $onlyMine = $request->boolean('mine') || ! $isAdmin;

        $base = WorkOrderOperation::query()
            ->when($onlyMine, fn ($q) => $q->where('assignee_id', $user->id))
            ->whereHas('workOrder', fn ($q) => $q->whereIn('status', ['released', 'in_progress']));

        $ops = (clone $base)
            ->with(['workOrder.product:id,code,name,unit', 'workCenter:id,code,name'])
            ->orderByRaw("case status when 'in_progress' then 0 else 1 end")
            ->orderBy('work_order_id')
            ->orderBy('op_no')
            ->paginate(20)
            ->withQueryString();

        $todayReports = ProductionReport::query()
            ->where('user_id', $user->id)
            ->whereDate('reported_at', now()->toDateString());

        $stat = [
            'waiting' => (clone $base)->where('status', 'dispatched')->count(),
            'running' => (clone $base)->where('status', 'in_progress')->count(),
            'today_count' => (clone $todayReports)->count(),
            'today_ok' => (float) (clone $todayReports)->sum('ok_qty'),
        ];

        return view('mes::my-tasks.index', [
            'ops' => $ops,
            'stat' => $stat,
            'isAdmin' => $isAdmin,
            'onlyMine' => $onlyMine,
            'me' => $user,
        ]);
    }

    /** 单工序报工页（手机优先） */
    public function report(WorkOrderOperation $operation): View
    {
        $operation->load(['workOrder.product:id,code,name,unit', 'workCenter:id,code,name']);

        $user = auth('mes')->user();
        if (! $user->hasRole('admin') && (int) $operation->assignee_id !== (int) $user->id) {
            abort(403, '这道工序不是派给你的。');
        }

        return view('mes::my-tasks.report', [
            'op' => $operation,
            'order' => $operation->workOrder,
            'remain' => max(0, (float) $operation->plan_qty - (float) $operation->done_qty),
            'shifts' => Shift::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'recent' => $operation->reports()->latest('reported_at')->limit(5)->get(),
        ]);
    }
}
