<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\Equipment;
use App\Modules\Mes\Models\MaintenanceOrder;
use App\Modules\Mes\Models\MaintenancePlan;
use App\Modules\Mes\Models\User;
use App\Modules\Mes\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 设备保养（MES，M7 增补）：保养计划 + 保养/维修工单。
 */
class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $service)
    {
    }

    public function plans(): View
    {
        $plans = MaintenancePlan::query()
            ->with('equipment:id,code,name,status')
            ->orderByRaw('next_due_at is null')
            ->orderBy('next_due_at')
            ->get();

        $orders = MaintenanceOrder::query()
            ->with(['equipment:id,code,name', 'plan:id,code,name', 'assignee:id,name'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest('id')
            ->limit(20)
            ->get();

        return view('mes::maintenance.plans', [
            'plans' => $plans,
            'orders' => $orders,
            'equipments' => Equipment::query()->orderBy('code')->get(['id', 'code', 'name']),
            'nextCode' => $this->service->nextPlanCode(),
            'stat' => [
                'total' => $plans->count(),
                'due' => $plans->filter(fn (MaintenancePlan $p) => $p->is_due)->count(),
                'open_orders' => $orders->count(),
                'done_month' => MaintenanceOrder::query()->where('status', 'done')->whereMonth('done_at', now()->month)->count(),
            ],
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', 'unique:mes.maintenance_plans,code'],
            'name' => ['required', 'string', 'max:64'],
            'equipment_id' => ['required', 'integer', 'exists:mes.equipments,id'],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'standard_minutes' => ['nullable', 'integer', 'min:1', 'max:6000'],
            'last_done_at' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:200'],
        ], [], [
            'code' => '计划编号', 'name' => '计划名称', 'equipment_id' => '设备',
            'cycle_days' => '周期（天）', 'standard_minutes' => '标准工时', 'last_done_at' => '上次保养',
        ]);

        $plan = $this->service->savePlan($data);

        return redirect()->route('mes.maintenance.plans')
            ->with('status', "保养计划 {$plan->code} 已建，下次到期 {$plan->next_due_at?->toDateString()}。");
    }

    public function updatePlan(Request $request, MaintenancePlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', 'unique:mes.maintenance_plans,code,' . $plan->id],
            'name' => ['required', 'string', 'max:64'],
            'equipment_id' => ['required', 'integer', 'exists:mes.equipments,id'],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'standard_minutes' => ['nullable', 'integer', 'min:1', 'max:6000'],
            'last_done_at' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:200'],
        ], [], ['code' => '计划编号', 'name' => '计划名称', 'equipment_id' => '设备', 'cycle_days' => '周期（天）']);

        $this->service->savePlan($data, $plan);

        return redirect()->route('mes.maintenance.plans')->with('status', "保养计划 {$plan->code} 已更新。");
    }

    public function generate(): RedirectResponse
    {
        $count = $this->service->generateDueOrders();

        return redirect()->route('mes.maintenance.plans')->with(
            $count > 0 ? 'status' : 'error',
            $count > 0 ? "已按到期计划生成 {$count} 张保养工单。" : '当前没有到期的保养计划。'
        );
    }

    public function orders(Request $request): View
    {
        $type = (string) $request->query('type', '');
        $status = (string) $request->query('status', '');

        $orders = MaintenanceOrder::query()
            ->with(['equipment:id,code,name', 'plan:id,code,name', 'assignee:id,name'])
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('mes::maintenance.orders', [
            'orders' => $orders,
            'type' => $type,
            'status' => $status,
            'assignees' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'stat' => [
                'pending' => MaintenanceOrder::query()->where('status', 'pending')->count(),
                'doing' => MaintenanceOrder::query()->where('status', 'in_progress')->count(),
                'done' => MaintenanceOrder::query()->where('status', 'done')->count(),
                'hours' => (float) MaintenanceOrder::query()->where('status', 'done')->sum('cost_hours'),
            ],
        ]);
    }

    public function complete(Request $request, MaintenanceOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'cost_hours' => ['required', 'numeric', 'min:0', 'max:999'],
            'remark' => ['nullable', 'string', 'max:200'],
        ], [], ['cost_hours' => '工时', 'remark' => '保养记录']);

        $this->service->complete($order, (float) $data['cost_hours'], $data['remark'] ?? null);

        return redirect()->route('mes.maintenance.orders')
            ->with('status', "保养单 {$order->order_no} 已完成，工时 {$data['cost_hours']} 小时。");
    }
}
