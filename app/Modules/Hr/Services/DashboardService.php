<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\AttendanceException;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeContract;
use App\Modules\Hr\Models\Leave;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Models\PayrollRun;
use App\Modules\Hr\Models\Requisition;
use App\Modules\Hr\Models\Position;

/**
 * 人力看板聚合（H6-01）。
 *
 * 口径：
 *   在职 = status=active 的人数；试用 = probation；离职 = left
 *   本月入职 / 本月离职 = hire_date / leave_date 落在本月
 *   待审请假 = leaves.status=pending
 *   待处理异常 = attendance_exceptions.status=open
 *   30 天内到期合同 = end_date 在 [today, today+30]
 */
class DashboardService
{
    public function snapshot(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $active = Employee::query()->where('status', 'active')->count();
        $probation = Employee::query()->where('status', 'probation')->count();
        $left = Employee::query()->where('status', 'left')->count();

        $hiredThisMonth = Employee::query()
            ->whereBetween('hire_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();
        $leftThisMonth = Employee::query()
            ->whereBetween('leave_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $pendingLeaves = Leave::query()->where('status', 'pending')->count();
        $openExceptions = AttendanceException::query()->where('status', 'open')->count();
        $pendingOvertimes = Overtime::query()->where('status', 'pending')->count();

        $contractsExpiring = EmployeeContract::query()
            ->where('status', 'active')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->count();

        // 部门在职人数 Top 5，用于看板横条
        $byDept = Employee::query()
            ->where('status', '!=', 'left')
            ->selectRaw('department_id, count(*) as n')
            ->groupBy('department_id')
            ->orderByDesc('n')
            ->limit(6)
            ->get();

        $deptNames = Department::query()->pluck('name', 'id');
        $deptRows = [];
        $maxDept = max(1, (int) ($byDept->max('n') ?? 1));
        foreach ($byDept as $row) {
            $deptRows[] = [
                'name' => (string) ($deptNames[$row->department_id] ?? '未分配'),
                'count' => (int) $row->n,
                'pct' => round((int) $row->n / $maxDept * 100, 1),
            ];
        }

        $pendingLeaveRows = Leave::query()
            ->with('employee:id,employee_no,name')
            ->where('status', 'pending')
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        $exceptionRows = AttendanceException::query()
            ->with('employee:id,employee_no,name')
            ->where('status', 'open')
            ->orderByDesc('work_date')
            ->limit(6)
            ->get();

        $expiringRows = EmployeeContract::query()
            ->with('employee:id,employee_no,name')
            ->where('status', 'active')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->orderBy('end_date')
            ->limit(5)
            ->get();

        $latestRun = PayrollRun::query()->orderByDesc('period')->first();

        $openReqs = Requisition::query()->where('status', 'open')->count();
        $candidates = Candidate::query()->count();
        $positionCount = Position::query()->count();

        return [
            'active' => $active,
            'probation' => $probation,
            'left' => $left,
            'total' => $active + $probation,
            'hired_month' => $hiredThisMonth,
            'left_month' => $leftThisMonth,
            'pending_leaves' => $pendingLeaves,
            'open_exceptions' => $openExceptions,
            'pending_overtimes' => $pendingOvertimes,
            'contracts_expiring' => $contractsExpiring,
            'departments' => $deptRows,
            'pending_leave_rows' => $pendingLeaveRows,
            'exception_rows' => $exceptionRows,
            'expiring_rows' => $expiringRows,
            'latest_run' => $latestRun,
            'open_reqs' => $openReqs,
            'candidates' => $candidates,
            'positions' => $positionCount,
        ];
    }
}
