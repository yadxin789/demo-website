<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Leave;
use App\Modules\Hr\Models\LeaveBalance;
use App\Modules\Hr\Services\LeaveAccrualService;
use App\Modules\Hr\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 请假与假期（H2-06 ~ H2-09）：列表、申请、审批、余额。
 */
class LeaveController extends Controller
{
    public function __construct(private readonly LeaveService $leaves)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $type = (string) $request->query('type', '');
        $q = trim((string) $request->query('q', ''));

        $rows = Leave::query()
            ->with([
                'employee:id,employee_no,name,department_id,leave_date',
                'employee.department:id,name',
                'approver:id,name',
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($q !== '', fn ($query) => $query->whereHas('employee', function ($inner) use ($q) {
                $inner->where('employee_no', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            }))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $counts = Leave::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('hr::leaves.index', [
            'rows' => $rows,
            'status' => $status,
            'type' => $type,
            'q' => $q,
            'kpi' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
                'days' => round((float) Leave::query()->where('status', 'approved')->sum('days'), 1),
            ],
            'typeText' => LeaveService::typeText(),
            'statusText' => LeaveService::statusText(),
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('hr::leaves.form', [
            'employees' => Employee::query()->where('status', '!=', 'left')->orderBy('employee_no')->get(['id', 'employee_no', 'name', 'department_id']),
            'typeText' => LeaveService::typeText(),
            'nextNo' => $this->leaves->nextLeaveNo(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr.employees,id'],
            'type' => ['required', Rule::in(['annual', 'sick', 'personal', 'compensatory'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:120'],
        ], [], [
            'employee_id' => '员工',
            'type' => '请假类型',
            'start_date' => '开始日期',
            'end_date' => '结束日期',
        ]);

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $leave = $this->leaves->apply($employee, $data);

        return redirect()->route('hr.leaves.index')
            ->with('status', "{$leave->leave_no} 已提交：{$employee->name} {$data['start_date']} ~ {$data['end_date']}，共 {$leave->days} 天，等待审批。");
    }

    public function approve(Request $request, Leave $leave): RedirectResponse
    {
        if ($leave->status !== 'pending') {
            return back()->with('error', '这条请假申请已经审批过了。');
        }

        $result = $this->leaves->approve($leave);

        $msg = "{$leave->leave_no} 已批准（{$leave->days} 天）。";
        if ($result['deducted'] > 0) {
            $msg .= '年假/调休余额扣减 ' . $result['deducted'] . ' 天，剩余 ' . $result['balance_left'] . ' 天。';
        } elseif (in_array($leave->type, ['annual', 'compensatory'], true)) {
            $msg .= '注意：没有找到对应年度的余额记录，未扣减。';
        }

        $pending = Leave::query()->where('status', 'pending')->count();

        return redirect()->route('hr.leaves.index')->with('status', $msg . "（待审批还剩 {$pending} 条）");
    }

    public function reject(Request $request, Leave $leave): RedirectResponse
    {
        if ($leave->status !== 'pending') {
            return back()->with('error', '这条请假申请已经审批过了。');
        }

        $data = $request->validate([
            'reject_reason' => ['required', 'string', 'max:120'],
        ], [], ['reject_reason' => '驳回原因']);

        $this->leaves->reject($leave, $data['reject_reason']);

        return redirect()->route('hr.leaves.index')
            ->with('status', "{$leave->leave_no} 已驳回：{$data['reject_reason']}。");
    }

    public function balances(Request $request): View
    {
        $year = (int) $request->query('year', (int) now()->format('Y'));
        $type = (string) $request->query('type', '');
        $deptId = (int) $request->query('dept', 0);
        $q = trim((string) $request->query('q', ''));

        $rows = LeaveBalance::query()
            ->with(['employee:id,employee_no,name,department_id', 'employee.department:id,name'])
            ->where('year', $year)
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($deptId > 0, fn ($query) => $query->whereHas('employee', fn ($inner) => $inner->where('department_id', $deptId)))
            ->when($q !== '', fn ($query) => $query->whereHas('employee', function ($inner) use ($q) {
                $inner->where('employee_no', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            }))
            ->orderBy('type')
            ->orderBy('employee_id')
            ->paginate(20)
            ->withQueryString();

        $sum = LeaveBalance::query()
            ->where('year', $year)
            ->selectRaw('type, sum(total) as t, sum(used) as u, sum(remaining) as r')
            ->groupBy('type')
            ->get();

        return view('hr::leaves.balances', [
            'rows' => $rows,
            'year' => $year,
            'type' => $type,
            'deptId' => $deptId,
            'q' => $q,
            'sum' => $sum->keyBy('type'),
            'typeText' => LeaveService::typeText(),
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
        ]);
    }

    /** 年假核算（按司龄规则算应享额度，先看差异再一键发放） */
    public function accrual(Request $request): View
    {
        $year = (int) $request->query('year', (int) now()->format('Y'));
        $rows = $this->accrualService()->preview($year);

        return view('hr::leaves.accrual', [
            'rows' => $rows,
            'year' => $year,
            'rules' => LeaveAccrualService::rules(),
            'stat' => [
                'people' => $rows->count(),
                'entitled_days' => round((float) $rows->sum('entitled'), 1),
                'need_adjust' => $rows->filter(fn ($r) => abs((float) $r['delta']) >= 0.01)->count(),
                'no_balance' => $rows->where('has_balance', false)->count(),
            ],
        ]);
    }

    /** 执行核算：写 leave_balances（type=annual），保留已休天数 */
    public function accrualRun(Request $request): RedirectResponse
    {
        $year = (int) $request->input('year', (int) now()->format('Y'));
        $stat = $this->accrualService()->run($year);

        return redirect()->route('hr.leaves.accrual', ['year' => $year])
            ->with('status', "{$year} 年年假核算完成：新增 {$stat['created']} 人、调整 {$stat['updated']} 人、无变化 {$stat['unchanged']} 人，合计授予 {$stat['total_days']} 天。");
    }

    private function accrualService(): LeaveAccrualService
    {
        return app(LeaveAccrualService::class);
    }
}
