<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers\Org;

use App\Modules\Hr\Http\Controllers\Controller;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\SalaryStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 岗位列表（H1-02）。
 *
 * 展示：岗位编码、名称、所属部门、职级、在职人数、岗位薪资标准。
 * 操作：新增 / 编辑岗位（编码唯一），带出该岗位现有薪资结构。
 */
class PositionController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $deptId = $request->query('dept');

        $rows = Position::query()
            ->with('department:id,name')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->when($deptId, fn ($query) => $query->where('department_id', (int) $deptId))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $filledByPos = Employee::query()
            ->where('status', '!=', 'left')
            ->selectRaw('position_id, count(*) as n')
            ->groupBy('position_id')
            ->pluck('n', 'position_id');

        $structures = SalaryStructure::query()->get()->keyBy('position_id');

        return view('hr::org.positions.index', [
            'rows' => $rows,
            'q' => $q,
            'deptId' => $deptId,
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
            'filledByPos' => $filledByPos,
            'structures' => $structures,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Position::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'department_id' => $data['department_id'] ?? null,
            'grade' => $data['grade'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        return redirect()->route('hr.org.positions.index')
            ->with('status', "岗位 {$data['code']} {$data['name']} 已新增。");
    }

    public function destroy(Position $position): RedirectResponse
    {
        if (Employee::query()->where('position_id', $position->id)->where('status', '!=', 'left')->exists()) {
            return back()->with('error', '这个岗位还有在职员工，先调岗再删除。');
        }

        $code = $position->code;
        $position->delete();

        return redirect()->route('hr.org.positions.index')
            ->with('status', "岗位 {$code} 已删除。");
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $data = $this->validated($request, $position);

        $position->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'department_id' => $data['department_id'] ?? null,
            'grade' => $data['grade'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        return redirect()->route('hr.org.positions.index')
            ->with('status', "岗位 {$position->code} 已更新。");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Position $position = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:16', Rule::unique('hr.positions', 'code')->ignore($position?->id)],
            'name' => ['required', 'string', 'max:64'],
            'department_id' => ['nullable', 'integer', 'exists:hr.departments,id'],
            'grade' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ], [], [
            'code' => '岗位编码',
            'name' => '岗位名称',
            'department_id' => '所属部门',
            'grade' => '职级',
        ]);
    }
}
