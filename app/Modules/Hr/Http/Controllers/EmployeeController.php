<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 员工档案（H1-05 ~ H1-07）。
 *
 * 列表：工号 / 姓名 / 部门 / 岗位 / 状态，支持关键字、部门、状态筛选。
 * 详情：基本信息 + 合同 + 异动 + 考勤 + 工资条 五块。
 * 增改：新增走入职建档（自动建工号、建合同、写 hire 异动）；修改部门/岗位时自动写 transfer 异动。
 */
class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $deptId = (int) $request->query('dept', 0);
        $status = (string) $request->query('status', '');

        $rows = Employee::query()
            ->with(['department:id,code,name', 'position:id,code,name,grade'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('employee_no', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($deptId > 0, fn ($query) => $query->where('department_id', $deptId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('employee_no')
            ->paginate(15)
            ->withQueryString();

        $kpi = [
            'active' => Employee::query()->where('status', 'active')->count(),
            'probation' => Employee::query()->where('status', 'probation')->count(),
            'left' => Employee::query()->where('status', 'left')->count(),
            'month_hire' => Employee::query()
                ->whereBetween('hire_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->count(),
        ];

        return view('hr::employees.index', [
            'rows' => $rows,
            'q' => $q,
            'deptId' => $deptId,
            'status' => $status,
            'kpi' => $kpi,
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
            'statusText' => EmployeeService::statusText(),
        ]);
    }

    public function show(Employee $employee): View
    {
        $employee->load([
            'department:id,code,name',
            'position:id,code,name,grade,department_id',
        ]);

        return view('hr::employees.show', [
            'employee' => $employee,
            'contracts' => $employee->contracts()->get(),
            'movements' => $employee->movements()->limit(10)->get(),
            'attendances' => $employee->attendances()->limit(10)->get(),
            'payrollItems' => $employee->payrollItems()->orderByDesc('id')->limit(6)->get(),
            'balances' => $employee->balances()->orderByDesc('year')->get(),
            'statusText' => EmployeeService::statusText(),
            'movementText' => EmployeeService::movementText(),
        ]);
    }

    public function create(): View
    {
        return view('hr::employees.form', [
            'employee' => null,
            'options' => $this->employees->formOptions(),
            'nextNo' => $this->employees->nextEmployeeNo(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $employee = $this->employees->hire($data);

        return redirect()->route('hr.employees.show', $employee->id)
            ->with('status', "已建档：{$employee->employee_no} {$employee->name}（工号与首份合同已自动生成）。");
    }

    public function edit(Employee $employee): View
    {
        return view('hr::employees.form', [
            'employee' => $employee,
            'options' => $this->employees->formOptions(),
            'nextNo' => $employee->employee_no,
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validated($request, $employee);

        $newDept = $data['department_id'] ?? null;
        $newPos = $data['position_id'] ?? null;
        $moved = ((int) $newDept !== (int) $employee->department_id)
            || ((int) $newPos !== (int) $employee->position_id);
        $before = $employee->status;

        $employee->fill([
            'name' => $data['name'],
            'gender' => $data['gender'] ?? $employee->gender,
            'birthday' => $data['birthday'] ?? null,
            'id_card_masked' => $data['id_card_masked'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'department_id' => $newDept,
            'position_id' => $newPos,
            'hire_date' => $data['hire_date'] ?? $employee->hire_date,
            'status' => $data['status'] ?? $employee->status,
            'education' => $data['education'] ?? null,
            'remark' => $data['remark'] ?? null,
        ])->save();

        // 在档案页把状态直接改成"离职"，也按离职流程走（写异动 + 回写编制）
        if ($before !== 'left' && ($data['status'] ?? $before) === 'left') {
            $this->employees->leave($employee, null, '档案页修改状态为离职');
        } elseif ($moved) {
            $this->employees->transfer($employee, $newDept, $newPos);
        } else {
            $this->employees->syncHeadcount($employee->department_id, $employee->position_id);
        }

        return redirect()->route('hr.employees.show', $employee->id)
            ->with('status', $moved ? '档案已更新，部门或岗位发生变化，已自动登记一条调岗异动。' : '档案已更新。');
    }

    /** 转正（试用 → 在职）。 */
    public function regularize(Request $request, Employee $employee): RedirectResponse
    {
        if ($employee->status !== 'probation') {
            return back()->with('error', '只有在试用状态的员工可以办转正。');
        }

        $this->employees->regularize($employee, $request->input('date') ?: null);

        return redirect()->route('hr.employees.show', $employee->id)
            ->with('status', "{$employee->name} 已转正（生效日 " . ($request->input('date') ?: now()->toDateString()) . '）。');
    }

    /** 离职。 */
    public function leave(Request $request, Employee $employee): RedirectResponse
    {
        if ($employee->status === 'left') {
            return back()->with('error', '这个员工已经是离职状态了。');
        }

        $this->employees->leave($employee, $request->input('date') ?: null, $request->input('reason') ?: null);

        return redirect()->route('hr.employees.show', $employee->id)
            ->with('status', "{$employee->name} 已办理离职，编制在职数已回写。");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'employee_no' => ['nullable', 'string', 'max:16', Rule::unique('hr.employees', 'employee_no')->ignore($employee?->id)],
            'name' => ['required', 'string', 'max:32'],
            'gender' => ['nullable', Rule::in(['female', 'male'])],
            'birthday' => ['nullable', 'date'],
            'id_card_masked' => ['nullable', 'string', 'max:24'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:64'],
            'department_id' => ['required', 'integer', 'exists:hr.departments,id'],
            'position_id' => ['required', 'integer', 'exists:hr.positions,id'],
            'hire_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['probation', 'active', 'left'])],
            'education' => ['nullable', 'string', 'max:16'],
            'remark' => ['nullable', 'string', 'max:200'],
            'contract_type' => ['nullable', Rule::in(['fixed', 'open'])],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
        ], [], [
            'name' => '姓名',
            'department_id' => '部门',
            'position_id' => '岗位',
            'hire_date' => '入职日期',
            'email' => '邮箱',
        ]);
    }
}
