<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers\Org;

use App\Modules\Hr\Http\Controllers\Controller;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Headcount;
use App\Modules\Hr\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 部门树（H1-01）。
 *
 * 展示：多级组织（集团 → 中心 → 部门），每行给出编制 / 在职 / 缺口。
 * 操作：新增部门、改名称与负责人、停用、删除（有下级或有在职员工时拒绝）。
 */
class DepartmentController extends Controller
{
    public function index(): View
    {
        $rows = Department::query()
            ->with('manager:id,name,employee_no')
            ->orderBy('level')
            ->orderBy('sort')
            ->get();

        // 在职人数（不含离职）与编制合计，按部门
        $filledByDept = Employee::query()
            ->where('status', '!=', 'left')
            ->selectRaw('department_id, count(*) as n')
            ->groupBy('department_id')
            ->pluck('n', 'department_id');

        $plannedByDept = Headcount::query()
            ->selectRaw('department_id, sum(planned) as n')
            ->groupBy('department_id')
            ->pluck('n', 'department_id');

        $tree = $this->buildTree($rows, $filledByDept, $plannedByDept);

        $kpi = [
            'departments' => $rows->count(),
            'planned' => (int) $plannedByDept->sum(),
            'filled' => (int) $filledByDept->sum(),
            'gap' => max(0, (int) $plannedByDept->sum() - (int) $filledByDept->sum()),
        ];

        return view('hr::org.departments.index', [
            'tree' => $tree,
            'kpi' => $kpi,
            'managers' => Employee::query()->whereIn('status', ['active', 'probation'])->orderBy('employee_no')->get(['id', 'name', 'employee_no']),
            'parents' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name', 'level']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $level = 1;
        if (! empty($data['parent_id'])) {
            $parent = Department::query()->find($data['parent_id']);
            $level = $parent ? $parent->level + 1 : 1;
        }

        Department::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'level' => $level,
            'status' => $data['status'] ?? 'active',
            'sort' => (int) ($data['sort'] ?? 0),
        ]);

        return redirect()->route('hr.org.departments.index')
            ->with('status', "部门 {$data['code']} {$data['name']} 已新增。");
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $this->validated($request, $department);

        $level = $department->level;
        if (($data['parent_id'] ?? null) !== $department->parent_id) {
            $level = 1;
            if (! empty($data['parent_id'])) {
                $parent = Department::query()->find($data['parent_id']);
                $level = $parent ? $parent->level + 1 : 1;
            }
        }

        $department->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'level' => $level,
            'status' => $data['status'] ?? 'active',
            'sort' => (int) ($data['sort'] ?? 0),
        ]);

        return redirect()->route('hr.org.departments.index')
            ->with('status', "部门 {$department->code} 已更新。");
    }

    public function destroy(Department $department): RedirectResponse
    {
        if (Department::query()->where('parent_id', $department->id)->exists()) {
            return back()->with('error', '这个部门下面还有下级部门，先处理下级再删除。');
        }

        if (Employee::query()->where('department_id', $department->id)->where('status', '!=', 'left')->exists()) {
            return back()->with('error', '这个部门还有在职员工，先调岗或办离职再删除。');
        }

        $department->delete();

        return redirect()->route('hr.org.departments.index')
            ->with('status', "部门 {$department->code} 已删除。");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:16', Rule::unique('hr.departments', 'code')->ignore($department?->id)],
            'name' => ['required', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer', 'exists:hr.departments,id'],
            'manager_id' => ['nullable', 'integer', 'exists:hr.employees,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'code' => '部门编码',
            'name' => '部门名称',
            'parent_id' => '上级部门',
            'manager_id' => '部门负责人',
        ]);
    }

    /**
     * 按 parent_id 组装树，并带上人数与缺口。
     *
     * @param  \Illuminate\Support\Collection  $rows
     */
    private function buildTree($rows, $filledByDept, $plannedByDept, ?int $parentId = null, int $depth = 0): array
    {
        $out = [];
        foreach ($rows as $row) {
            $rowParent = $row->parent_id !== null ? (int) $row->parent_id : null;
            if ($rowParent !== $parentId) {
                continue;
            }

            $filled = (int) ($filledByDept[$row->id] ?? 0);
            $planned = (int) ($plannedByDept[$row->id] ?? 0);

            $out[] = [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'level' => $row->level,
                'status' => $row->status,
                'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
                'manager_id' => $row->manager_id !== null ? (int) $row->manager_id : null,
                'sort' => (int) $row->sort,
                'manager' => $row->manager?->name,
                'filled' => $filled,
                'planned' => $planned,
                'gap' => max(0, $planned - $filled),
                'depth' => $depth,
                'children' => $this->buildTree($rows, $filledByDept, $plannedByDept, $row->id, $depth + 1),
            ];
        }

        return $out;
    }
}
