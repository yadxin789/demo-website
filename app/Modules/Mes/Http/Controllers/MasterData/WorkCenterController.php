<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\MasterData;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 工作中心（M1-04）：车间/产线 + 日产能（分钟）。
 */
class WorkCenterController extends Controller
{
    public function index(): View
    {
        $centers = WorkCenter::query()->orderBy('code')->get();

        // 每个工作中心挂了多少工序，用来说明产能压力
        $loads = $centers->mapWithKeys(fn ($c) => [$c->id => $c->operations()->whereIn('status', ['pending', 'dispatched', 'in_progress'])->count()]);

        return view('mes::master.work-centers.index', [
            'centers' => $centers,
            'loads' => $loads,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        WorkCenter::query()->create($this->validated($request));

        return redirect()->route('mes.master.work-centers.index')->with('status', '工作中心已新增。');
    }

    public function update(Request $request, WorkCenter $workCenter): RedirectResponse
    {
        $workCenter->fill($this->validated($request, $workCenter->id))->save();

        return redirect()->route('mes.master.work-centers.index')->with('status', '工作中心已更新。');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:32', "unique:mes.work_centers,code,{$id}"],
            'name' => ['required', 'string', 'max:120'],
            'workshop' => ['nullable', 'string', 'max:64'],
            'daily_capacity' => ['required', 'integer', 'min:1', 'max:1440'],
            'status' => ['required', Rule::in(['active', 'idle', 'maintenance'])],
        ], [], [
            'code' => '编码',
            'name' => '名称',
            'daily_capacity' => '日产能（分钟）',
            'status' => '状态',
        ]);
    }
}
