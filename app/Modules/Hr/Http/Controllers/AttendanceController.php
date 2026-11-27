<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\AttendanceException;
use App\Modules\Hr\Models\AttendanceRecord;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 考勤（H2-01 ~ H2-05）：班次、打卡记录、异常处理、加班审批。
 */
class AttendanceController extends Controller
{
    /** @var array<string, string> */
    private const RECORD_STATUS = [
        'normal' => '正常',
        'late' => '迟到',
        'early' => '早退',
        'absent' => '缺勤',
        'missing' => '缺卡',
    ];

    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    // ---------- 班次 ----------
    public function shifts(): View
    {
        return view('hr::attendance.shifts', [
            'rows' => Shift::query()->orderBy('code')->get(),
            'recordCounts' => AttendanceRecord::query()
                ->selectRaw('shift_id, count(*) as n')
                ->groupBy('shift_id')
                ->pluck('n', 'shift_id'),
        ]);
    }

    public function shiftStore(Request $request): RedirectResponse
    {
        $data = $this->validateShift($request);

        Shift::query()->create($data + ['status' => $data['status'] ?? 'active']);

        return redirect()->route('hr.attendance.shifts')->with('status', "班次 {$data['code']} 已新增。");
    }

    public function shiftUpdate(Request $request, Shift $shift): RedirectResponse
    {
        $data = $this->validateShift($request, $shift);

        $shift->update($data + ['status' => $data['status'] ?? 'active']);

        return redirect()->route('hr.attendance.shifts')->with('status', "班次 {$shift->code} 已更新。");
    }

    // ---------- 打卡记录 ----------
    public function records(Request $request): View
    {
        $month = (string) $request->query('month', now()->format('Y-m'));
        $deptId = (int) $request->query('dept', 0);
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $from = Carbon::parse($month . '-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $rows = AttendanceRecord::query()
            ->with(['employee:id,employee_no,name,department_id', 'employee.department:id,name', 'shift:id,code,name'])
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->when($deptId > 0, fn ($query) => $query->whereHas('employee', fn ($inner) => $inner->where('department_id', $deptId)))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->whereHas('employee', function ($inner) use ($q) {
                $inner->where('employee_no', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            }))
            ->orderByDesc('work_date')
            ->orderBy('employee_id')
            ->paginate(20)
            ->withQueryString();

        return view('hr::attendance.records', [
            'rows' => $rows,
            'month' => $month,
            'deptId' => $deptId,
            'status' => $status,
            'q' => $q,
            'statusText' => self::RECORD_STATUS,
            'kpi' => $this->attendance->stats($from, $to, $deptId),
            'trend' => $this->attendance->dailyTrend($from, $to, $deptId),
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
        ]);
    }

    // ---------- 考勤异常 ----------
    public function exceptions(Request $request): View
    {
        $status = (string) $request->query('status', 'open');
        $type = (string) $request->query('type', '');
        $deptId = (int) $request->query('dept', 0);

        $rows = AttendanceException::query()
            ->with(['employee:id,employee_no,name,department_id', 'employee.department:id,name', 'handler:id,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($deptId > 0, fn ($query) => $query->whereHas('employee', fn ($inner) => $inner->where('department_id', $deptId)))
            ->orderByDesc('work_date')
            ->paginate(20)
            ->withQueryString();

        $counts = AttendanceException::query()
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return view('hr::attendance.exceptions', [
            'rows' => $rows,
            'status' => $status,
            'type' => $type,
            'deptId' => $deptId,
            'kpi' => [
                'open' => (int) ($counts['open'] ?? 0),
                'handled' => (int) ($counts['handled'] ?? 0),
                'total' => (int) $counts->sum(),
            ],
            'typeText' => ['late' => '迟到', 'early' => '早退', 'missing' => '缺卡'],
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
        ]);
    }

    public function exceptionHandle(Request $request, AttendanceException $exception): RedirectResponse
    {
        if ($exception->status === 'handled') {
            return back()->with('error', '这条异常已经处理过了。');
        }

        $data = $request->validate([
            'result' => ['required', Rule::in(['补卡', '不计', '扣款', '调休'])],
            'remark' => ['nullable', 'string', 'max:120'],
        ], [], ['result' => '处理方式']);

        $exception->update([
            'status' => 'handled',
            'result' => $data['result'],
            'reason' => $exception->reason ?? '考勤异常',
            'handler_id' => auth('hr')->id(),
            'handled_at' => now(),
        ]);

        $left = AttendanceException::query()->where('status', 'open')->count();

        return redirect()->route('hr.attendance.exceptions')
            ->with('status', "已按「{$data['result']}」处理，待处理还剩 {$left} 条。");
    }

    // ---------- 加班 ----------
    public function overtimes(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $type = (string) $request->query('type', '');

        $rows = Overtime::query()
            ->with(['employee:id,employee_no,name,department_id', 'employee.department:id,name', 'approver:id,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->orderByDesc('date')
            ->paginate(20)
            ->withQueryString();

        $counts = Overtime::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $hours = Overtime::query()->where('status', 'approved')->sum('hours');

        return view('hr::attendance.overtimes', [
            'rows' => $rows,
            'status' => $status,
            'type' => $type,
            'kpi' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
                'hours' => round((float) $hours, 1),
            ],
            'typeText' => ['workday' => '工作日', 'weekend' => '休息日', 'holiday' => '法定节假日'],
            'employees' => Employee::query()->where('status', '!=', 'left')->orderBy('employee_no')->get(['id', 'employee_no', 'name']),
        ]);
    }

    public function overtimeStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr.employees,id'],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'type' => ['required', Rule::in(['workday', 'weekend', 'holiday'])],
            'reason' => ['nullable', 'string', 'max:120'],
        ], [], [
            'employee_id' => '员工',
            'date' => '加班日期',
            'hours' => '小时数',
            'type' => '加班类型',
        ]);

        Overtime::query()->create($data + ['status' => 'pending']);

        return redirect()->route('hr.attendance.overtimes')
            ->with('status', '加班申请已提交，状态待审批。');
    }

    public function overtimeApprove(Request $request, Overtime $overtime): RedirectResponse
    {
        return $this->overtimeDecide($overtime, 'approve');
    }

    public function overtimeReject(Request $request, Overtime $overtime): RedirectResponse
    {
        return $this->overtimeDecide($overtime, 'reject');
    }

    private function overtimeDecide(Overtime $overtime, string $action): RedirectResponse
    {
        if ($overtime->status !== 'pending') {
            return back()->with('error', '这条加班申请已经审批过了。');
        }

        $overtime->update([
            'status' => $action === 'approve' ? 'approved' : 'rejected',
            'approver_id' => auth('hr')->id(),
            'approved_at' => now(),
        ]);

        return redirect()->route('hr.attendance.overtimes')
            ->with('status', $action === 'approve' ? '加班已批准。' : '加班已驳回。');
    }

    /** @return array<string, mixed> */
    private function validateShift(Request $request, ?Shift $shift = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:8', Rule::unique('hr.shifts', 'code')->ignore($shift?->id)],
            'name' => ['required', 'string', 'max:32'],
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i'],
            'late_tolerance_min' => ['nullable', 'integer', 'min:0', 'max:60'],
            'cross_day' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ], [], [
            'code' => '班次编码',
            'name' => '班次名称',
            'work_start' => '上班时间',
            'work_end' => '下班时间',
        ]);
    }
}
