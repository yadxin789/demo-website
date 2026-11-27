<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeContract;
use App\Modules\Hr\Services\EmployeeService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 合同与到期（H1-09）。
 *
 * 列表按剩余天数分色：≤30 天红、≤60 天橙、≤90 天黄，其余绿。
 * 操作：给员工续签一份新合同（自动生成合同号）。
 */
class ContractController extends Controller
{
    public function __construct(private readonly EmployeeService $employees)
    {
    }

    public function index(Request $request): View
    {
        $window = (int) $request->query('window', 0); // 0=全部，30/60/90=只看该窗口内到期
        $deptId = (int) $request->query('dept', 0);
        $q = trim((string) $request->query('q', ''));

        $today = Carbon::today();
        $rows = EmployeeContract::query()
            ->with(['employee:id,employee_no,name,department_id,position_id', 'employee.department:id,name', 'employee.position:id,name'])
            ->where('status', 'active')
            ->when($deptId > 0, fn ($query) => $query->whereHas('employee', fn ($inner) => $inner->where('department_id', $deptId)))
            ->when($q !== '', fn ($query) => $query->whereHas('employee', function ($inner) use ($q) {
                $inner->where('employee_no', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            }))
            ->when($window > 0, function ($query) use ($today, $window) {
                $query->whereNotNull('end_date')
                    ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays($window)->toDateString()]);
            })
            ->orderBy('end_date')
            ->paginate(15)
            ->withQueryString();

        $kpi = [
            'active' => EmployeeContract::query()->where('status', 'active')->count(),
            'd30' => $this->dueWithin(30),
            'd60' => $this->dueWithin(60),
            'd90' => $this->dueWithin(90),
        ];

        return view('hr::contracts.index', [
            'rows' => $rows,
            'q' => $q,
            'deptId' => $deptId,
            'window' => $window,
            'kpi' => $kpi,
            'today' => $today,
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
            'employees' => Employee::query()->where('status', '!=', 'left')->orderBy('employee_no')->get(['id', 'employee_no', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr.employees,id'],
            'type' => ['required', Rule::in(['fixed', 'open'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ], [], [
            'employee_id' => '员工',
            'type' => '合同类型',
            'start_date' => '起始日',
            'end_date' => '到期日',
        ]);

        if ($data['type'] === 'fixed' && empty($data['end_date'])) {
            return back()->with('error', '固定期限合同必须填到期日。');
        }

        EmployeeContract::query()->create([
            'employee_id' => $data['employee_id'],
            'contract_no' => $this->employees->nextContractNo(),
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['type'] === 'open' ? null : $data['end_date'],
            'sign_date' => $data['start_date'],
            'status' => 'active',
            'remark' => '演示环境新增',
        ]);

        return redirect()->route('hr.employees.contracts')
            ->with('status', '合同已登记，合同号自动生成。');
    }

    private function dueWithin(int $days): int
    {
        $today = Carbon::today();

        return EmployeeContract::query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays($days)->toDateString()])
            ->count();
    }
}
