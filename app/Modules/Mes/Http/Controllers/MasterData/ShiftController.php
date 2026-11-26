<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\MasterData;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 班次（M1-05）：白班 / 夜班，支持跨天。
 */
class ShiftController extends Controller
{
    public function index(): View
    {
        return view('mes::master.shifts.index', [
            'shifts' => Shift::query()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Shift::query()->create($this->validated($request) + ['is_active' => true]);

        return redirect()->route('mes.master.shifts.index')->with('status', '班次已新增。');
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $shift->fill($this->validated($request, $shift->id));
        $shift->is_active = $request->boolean('is_active');
        $shift->save();

        return redirect()->route('mes.master.shifts.index')->with('status', '班次已更新。');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:16', "unique:mes.shifts,code,{$id}"],
            'name' => ['required', 'string', 'max:32'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'cross_day' => ['nullable', 'boolean'],
        ], [], [
            'code' => '编码',
            'name' => '名称',
            'start_time' => '开始时间',
            'end_time' => '结束时间',
        ]);
    }
}
