<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\Equipment;
use App\Modules\Mes\Models\EquipmentCheck;
use App\Modules\Mes\Models\EquipmentRepair;
use App\Modules\Mes\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 设备管理（M5）。
 *
 * 台账 → 每日点检 → 故障报修 → 修复回运行，一条链路。
 * 设备状态与报修状态互相带动：
 *   报修 open/handling → 设备 repairing；修复 fixed/closed → 设备 running。
 */
class EquipmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $wc = (string) $request->query('work_center', '');
        $q = trim((string) $request->query('q', ''));

        $equipments = Equipment::query()
            ->with(['workCenter:id,code,name'])
            ->withCount(['repairs as open_repairs' => fn ($b) => $b->whereIn('status', ['open', 'handling'])])
            ->when($status !== '', fn ($b) => $b->where('status', $status))
            ->when($wc !== '', fn ($b) => $b->where('work_center_id', (int) $wc))
            ->when($q !== '', function ($b) use ($q) {
                $b->where(function ($inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $base = Equipment::query();

        return view('mes::equipment.index', [
            'equipments' => $equipments,
            'status' => $status,
            'wc' => $wc,
            'q' => $q,
            'centers' => WorkCenter::query()->orderBy('code')->get(['id', 'code', 'name']),
            'stat' => [
                'total' => (clone $base)->count(),
                'running' => (clone $base)->where('status', 'running')->count(),
                'idle' => (clone $base)->where('status', 'idle')->count(),
                'repairing' => (clone $base)->where('status', 'repairing')->count(),
                'scrapped' => (clone $base)->where('status', 'scrapped')->count(),
                'checked_today' => EquipmentCheck::query()->whereDate('checked_at', now()->toDateString())->distinct('equipment_id')->count('equipment_id'),
            ],
        ]);
    }

    public function show(Equipment $equipment): View
    {
        $equipment->load([
            'workCenter:id,code,name',
            'checks' => fn ($q) => $q->latest('checked_at')->limit(20),
            'checks.checker:id,name',
            'repairs' => fn ($q) => $q->latest('id')->limit(10),
            'repairs.reporter:id,name',
            'repairs.handler:id,name',
        ]);

        return view('mes::equipment.show', [
            'eq' => $equipment,
            'handlers' => \App\Modules\Mes\Models\User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'centers' => WorkCenter::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'work_center_id' => ['required', 'integer', 'exists:mes.work_centers,id'],
            'status' => ['required', 'in:running,idle,repairing,scrapped'],
            'check_cycle_days' => ['required', 'integer', 'min:1', 'max:365'],
            'remark' => ['nullable', 'string', 'max:200'],
        ], [], [
            'name' => '设备名称',
            'work_center_id' => '工作中心',
            'status' => '状态',
            'check_cycle_days' => '点检周期',
        ]);

        $equipment->update($data);

        return redirect()->route('mes.equipment.show', $equipment)
            ->with('status', "设备 {$equipment->code} 资料已更新。");
    }

    /** 每日点检 */
    public function check(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate([
            'result' => ['required', 'in:ok,ng'],
            'remark' => ['nullable', 'string', 'max:200'],
        ], [], ['result' => '点检结果']);

        EquipmentCheck::query()->create([
            'equipment_id' => $equipment->id,
            'checked_at' => now(),
            'checker_id' => (int) auth('mes')->id(),
            'result' => $data['result'],
            'remark' => $data['remark'] ?? null,
        ]);

        $equipment->update(['last_check_at' => now()]);

        return redirect()->route('mes.equipment.show', $equipment)
            ->with(
                $data['result'] === 'ok' ? 'status' : 'error',
                $data['result'] === 'ok'
                    ? "设备 {$equipment->code} 点检正常，已记录。"
                    : "设备 {$equipment->code} 点检发现异常，建议立即报修并停机检查。"
            );
    }

    /** 报修：建单并把设备置为维修中 */
    public function repair(Request $request, Equipment $equipment): RedirectResponse
    {
        $data = $request->validate([
            'fault_desc' => ['required', 'string', 'max:200'],
            'handler_id' => ['nullable', 'integer', 'exists:mes.users,id'],
        ], [], ['fault_desc' => '故障描述']);

        $repair = EquipmentRepair::query()->create([
            'equipment_id' => $equipment->id,
            'fault_desc' => $data['fault_desc'],
            'reported_by' => (int) auth('mes')->id(),
            'handler_id' => $data['handler_id'] ?? null,
            'down_minutes' => 0,
            'status' => 'open',
        ]);

        $equipment->update(['status' => 'repairing']);

        return redirect()->route('mes.equipment.show', $equipment)
            ->with('status', "已报修：{$repair->fault_desc}。设备已置为维修中，修好后在报修记录里点「修复」。");
    }

    /** 报修流转：受理 / 修复 / 关单 */
    public function repairUpdate(Request $request, EquipmentRepair $repair): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,handling,fixed,closed'],
            'handler_id' => ['nullable', 'integer', 'exists:mes.users,id'],
            'down_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [], ['status' => '报修状态']);

        $repair->update([
            'status' => $data['status'],
            'handler_id' => $data['handler_id'] ?? $repair->handler_id,
            'down_minutes' => $data['down_minutes'] ?? $repair->down_minutes,
            'fixed_at' => in_array($data['status'], ['fixed', 'closed'], true) ? now() : null,
        ]);

        // 修复/关单后设备回到运行；仍有别的未闭环报修则保持维修中
        $stillOpen = EquipmentRepair::query()
            ->where('equipment_id', $repair->equipment_id)
            ->whereIn('status', ['open', 'handling'])
            ->exists();

        if (! $stillOpen) {
            Equipment::query()->whereKey($repair->equipment_id)->update(['status' => 'running']);
        }

        $label = ['open' => '未受理', 'handling' => '处理中', 'fixed' => '已修复', 'closed' => '已关单'][$data['status']] ?? $data['status'];

        return redirect()->route('mes.equipment.show', $repair->equipment_id)
            ->with('status', "报修单已更新为「{$label}」" . ($stillOpen ? '，设备仍有未闭环报修。' : '，设备已恢复运行。'));
    }
}
