<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\System;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 系统 · 部门（S12-M03）。
 *
 * 两级结构即可满足演示：parent_id 为空是厂级/中心，非空是车间/班组。
 * 有子部门或有在职用户时不允许删除，避免演示数据出现悬空引用。
 */
class DepartmentController extends Controller
{
    public function index(): View
    {
        $this->ensureAdmin();

        return view('mes::system.departments.index', [
            'departments' => Department::query()
                ->with('parent:id,name')
                ->withCount(['users', 'positions', 'children'])
                ->orderBy('sort')->orderBy('id')->get(),
            'parents' => Department::query()->orderBy('sort')->orderBy('id')->get(),
            'tree' => Department::query()->with('children')->whereNull('parent_id')->orderBy('sort')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $this->validated($request);

        $department = Department::query()->create($data);

        return redirect()->route('mes.system.departments.index')
            ->with('status', "已新增部门「{$department->name}」。");
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $this->validated($request, $department);

        if ($data['parent_id'] === $department->id) {
            return back()->withErrors(['parent_id' => '上级部门不能是自己。']);
        }

        $department->update($data);

        return redirect()->route('mes.system.departments.index')
            ->with('status', "部门「{$department->name}」已保存。");
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->ensureAdmin();

        if ($department->children()->exists()) {
            return back()->withErrors(['department' => '该部门下还有子部门，请先处理子部门。']);
        }
        if ($department->users()->exists()) {
            return back()->withErrors(['department' => '该部门下还有用户，请先把用户调到其他部门。']);
        }

        $name = $department->name;
        $department->positions()->delete();
        $department->delete();

        return redirect()->route('mes.system.departments.index')->with('status', "部门「{$name}」已删除。");
    }

    /** @return array{name:string, parent_id:int|null, sort:int, status:string} */
    private function validated(Request $request, ?Department $department = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('mes.departments', 'name')->ignore($department?->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('mes.departments', 'id')],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', 'in:active,disabled'],
        ], [], ['name' => '部门名称', 'parent_id' => '上级部门', 'sort' => '排序', 'status' => '状态']);

        return [
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'sort' => (int) ($data['sort'] ?? 0),
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
