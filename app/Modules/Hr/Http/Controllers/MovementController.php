<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeMovement;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 异动记录（H1-10）。
 *
 * 列表：入职 / 转正 / 调岗 / 晋升 / 离职 流水，按生效日倒排。
 * 登记：只允许"转正""调岗""离职"三类人工登记（入职由建档自动写，避免重复）。
 * 调岗与离职走 EmployeeService，保证档案与编制同步。
 */
class MovementController extends Controller
{
    public function __construct(private readonly EmployeeService $employees)
    {
    }

    public function index(Request $request): View
    {
        $type = (string) $request->query('type', '');
        $q = trim((string) $request->query('q', ''));

        $rows = EmployeeMovement::query()
            ->with([
                'employee:id,employee_no,name,department_id,position_id',
                'fromDepartment:id,name',
                'toDepartment:id,name',
                'fromPosition:id,name',
                'toPosition:id,name',
            ])
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($q !== '', fn ($query) => $query->whereHas('employee', function ($inner) use ($q) {
                $inner->where('employee_no', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            }))
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('hr::movements.index', [
            'rows' => $rows,
            'type' => $type,
            'q' => $q,
            'movementText' => EmployeeService::movementText(),
            'employees' => Employee::query()->orderBy('employee_no')->get(['id', 'employee_no', 'name', 'department_id', 'position_id', 'status']),
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
            'positions' => Position::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr.employees,id'],
            'type' => ['required', Rule::in(['regular', 'transfer', 'leave'])],
            'effective_date' => ['required', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:hr.departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:hr.positions,id'],
            'reason' => ['nullable', 'string', 'max:120'],
        ], [], [
            'employee_id' => '员工',
            'type' => '异动类型',
            'effective_date' => '生效日',
        ]);

        $employee = Employee::query()->findOrFail($data['employee_id']);

        if ($data['type'] === 'regular' && $employee->status !== 'probation') {
            return back()->with('error', '这个员工不在试用状态，不能登记转正。');
        }

        if ($data['type'] === 'transfer' && empty($data['department_id']) && empty($data['position_id'])) {
            return back()->with('error', '调岗至少要指定新部门或新岗位。');
        }

        if ($data['type'] === 'leave' && $employee->status === 'left') {
            return back()->with('error', '这个员工已经是离职状态了。');
        }

        match ($data['type']) {
            'regular' => $this->employees->regularize($employee, $data['effective_date']),
            'transfer' => $this->employees->transfer(
                $employee,
                isset($data['department_id']) ? (int) $data['department_id'] : $employee->department_id,
                isset($data['position_id']) ? (int) $data['position_id'] : $employee->position_id,
                $data['effective_date']
            ),
            'leave' => $this->employees->leave($employee, $data['effective_date'], $data['reason'] ?? null),
        };

        return redirect()->route('hr.employees.movements')
            ->with('status', '异动已登记，员工档案与编制在职数已同步。');
    }
}
