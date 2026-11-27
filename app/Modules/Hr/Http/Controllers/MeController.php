<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\AttendanceRecord;
use App\Modules\Hr\Models\Certificate;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveBalance;
use App\Modules\Hr\Models\PayrollItem;
use App\Modules\Hr\Models\ShiftAssignment;
use App\Modules\Hr\Models\TrainingRecord;
use Illuminate\View\View;

/**
 * 员工自助（HR，M7 增补）。
 *
 * 对标 OrangeHRM ESS / Odoo My HR：员工自己看考勤、假期、排班、培训、证书与工资条，
 * 不再需要 HR 代查。用登录账号的 employee_no 关联员工档案。
 */
class MeController extends Controller
{
    public function index(): View
    {
        $user = auth('hr')->user();
        $employeeNo = (string) ($user?->employee_no ?? '');

        $employee = $employeeNo !== ''
            ? Employee::query()->with(['department:id,name', 'position:id,name', 'contracts'])->where('employee_no', $employeeNo)->first()
            : null;

        if (! $employee) {
            return view('hr::me.index', [
                'employee' => null,
                'user' => $user,
            ]);
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $attendance = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->orderByDesc('work_date')
            ->limit(10)
            ->get();

        $monthStats = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->selectRaw('COUNT(*) as days, SUM(late_minutes) as late, SUM(early_minutes) as early')
            ->first();

        $balances = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('year')
            ->limit(6)
            ->get();

        $roster = ShiftAssignment::query()
            ->with('shift:id,code,name,start_time,end_time')
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->orderBy('work_date')
            ->get();

        $trainings = TrainingRecord::query()
            ->with('session:id,session_no,start_date,course_id', 'session.course:id,code,title,hours')
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $certificates = Certificate::query()
            ->where('employee_id', $employee->id)
            ->orderByRaw('expire_on is null')
            ->orderBy('expire_on')
            ->get();

        $latestPayroll = PayrollItem::query()
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->first();

        $contract = $employee->contracts->first();

        return view('hr::me.index', [
            'employee' => $employee,
            'user' => $user,
            'attendance' => $attendance,
            'monthStats' => $monthStats,
            'balances' => $balances,
            'roster' => $roster,
            'trainings' => $trainings,
            'certificates' => $certificates,
            'latestPayroll' => $latestPayroll,
            'contract' => $contract,
        ]);
    }
}
