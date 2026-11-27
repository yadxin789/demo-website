<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\System;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\Department;
use App\Modules\Mes\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 系统 · 岗位（S12-M04）。岗位挂在部门下，与用户一一对应。
 */
class PositionController extends Controller
{
    public function index(): View
    {
        $this->ensureAdmin();

        return view('mes::system.positions.index', [
            'positions' => Position::query()->with('department:id,name')->withCount('users')->orderBy('id')->get(),
            'departments' => Department::query()->active()->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $this->validated($request);

        $position = Position::query()->create($data);

        return redirect()->route('mes.system.positions.index')
            ->with('status', "已新增岗位「{$position->name}」。");
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $this->ensureAdmin();
        $position->update($this->validated($request, $position));

        return redirect()->route('mes.system.positions.index')
            ->with('status', "岗位「{$position->name}」已保存。");
    }

    public function destroy(Position $position): RedirectResponse
    {
        $this->ensureAdmin();

        if ($position->users()->exists()) {
            return back()->withErrors(['position' => '该岗位下还有用户，请先调整用户岗位。']);
        }

        $name = $position->name;
        $position->delete();

        return redirect()->route('mes.system.positions.index')->with('status', "岗位「{$name}」已删除。");
    }

    /** @return array{name:string, department_id:int|null, status:string} */
    private function validated(Request $request, ?Position $position = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('mes.positions', 'name')->ignore($position?->id)],
            'department_id' => ['nullable', 'integer', Rule::exists('mes.departments', 'id')],
            'status' => ['required', 'in:active,disabled'],
        ], [], ['name' => '岗位名称', 'department_id' => '所属部门', 'status' => '状态']);

        return [
            'name' => $data['name'],
            'department_id' => $data['department_id'] ?? null,
            'status' => $data['status'],
        ];
    }

    private function ensureAdmin(): void
    {
        if (! auth('mes')->user()?->hasRole('admin')) {
            abort(403, '系统页只对系统管理员开放。');
        }
    }
}
