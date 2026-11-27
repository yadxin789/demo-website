<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\AttendanceRecord;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Leave;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Models\PayrollItem;
use App\Modules\Hr\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 报表（H5-01）：人数结构、考勤汇总、人力成本。
 *
 * 只读聚合，不做写操作；口径都在注释里写死，页面上也展示原文，避免演示时被追问。
 */
class ReportController extends Controller
{
    /** 在册 = 在职 + 试用 */
    private const ACTIVE_STATUS = ['active', 'probation'];

    public function headcount(): View
    {
        $activeCount = Employee::query()->whereIn('status', self::ACTIVE_STATUS)->count();
        $leftCount = Employee::query()->where('status', 'left')->count();
        $probationCount = Employee::query()->where('status', 'probation')->count();

        $byStatus = Employee::query()
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $byDept = Employee::query()
            ->whereIn('status', self::ACTIVE_STATUS)
            ->selectRaw('department_id, count(*) as n')
            ->groupBy('department_id')
            ->get();

        $depts = DB::connection('hr')->table('departments')->orderBy('level')->orderBy('sort')->get(['id', 'name', 'code']);

        $rows = [];
        foreach ($depts as $d) {
            $n = (int) ($byDept->firstWhere('department_id', $d->id)->n ?? 0);
            $planned = (int) DB::connection('hr')->table('headcounts')->where('department_id', $d->id)->sum('planned');
            $rows[] = ['code' => $d->code, 'name' => $d->name, 'n' => $n, 'planned' => $planned];
        }

        $byEducation = Employee::query()
            ->whereIn('status', self::ACTIVE_STATUS)
            ->selectRaw('education, count(*) as n')
            ->groupBy('education')
            ->orderByDesc('n')
            ->pluck('n', 'education');

        // 司龄：按入职日分四段
        $tenure = ['< 1 年' => 0, '1 ~ 3 年' => 0, '3 ~ 5 年' => 0, '≥ 5 年' => 0];
        $hireDates = Employee::query()->whereIn('status', self::ACTIVE_STATUS)->pluck('hire_date');
        $now = now();
        foreach ($hireDates as $hireDate) {
            if ($hireDate === null) {
                continue;
            }
            $months = (int) $hireDate->diffInMonths($now);
            $tenure[$months < 12 ? '< 1 年' : ($months < 36 ? '1 ~ 3 年' : ($months < 60 ? '3 ~ 5 年' : '≥ 5 年'))]++;
        }

        $gradeRows = DB::connection('hr')->table('positions')
            ->selectRaw('grade, count(*) as n')
            ->groupBy('grade')
            ->orderBy('grade')
            ->get();

        $avgTenure = $hireDates->filter()->count() > 0
            ? round($hireDates->filter()->avg(fn ($d) => $d->diffInMonths($now)) / 12, 1)
            : 0;

        return view('hr::reports.headcount', [
            'kpi' => [
                'active' => $activeCount,
                'probation' => $probationCount,
                'left' => $leftCount,
                'avg_tenure' => $avgTenure,
                'depts' => count($rows),
            ],
            'rows' => $rows,
            'byStatus' => $byStatus,
            'statusText' => ['active' => '在职', 'probation' => '试用', 'left' => '离职'],
            'byEducation' => $byEducation,
            'tenure' => $tenure,
            'gradeRows' => $gradeRows,
        ]);
    }

    public function attendance(Request $request): View
    {
        $month = (string) $request->query('month', now()->format('Y-m'));
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        $all = AttendanceRecord::query()
            ->whereBetween('work_date', [$start, $end])
            ->get(['status', 'late_minutes', 'early_minutes', 'employee_id']);

        $statusCounts = $all->countBy('status');
        $total = $all->count();
        $normal = (int) ($statusCounts['normal'] ?? 0);

        $byDept = DB::connection('hr')->table('attendance_records as r')
            ->join('employees as e', 'e.id', '=', 'r.employee_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->whereBetween('r.work_date', [$start, $end])
            ->selectRaw('d.name as dept, count(*) as total, sum(case when r.status = ? then 1 else 0 end) as normal, sum(r.late_minutes) as late_min', ['normal'])
            ->groupBy('d.name')
            ->orderByDesc('total')
            ->get();

        $exceptions = DB::connection('hr')->table('attendance_exceptions')
            ->whereBetween('work_date', [$start, $end])
            ->selectRaw('type, count(*) as n')
            ->groupBy('type')
            ->pluck('n', 'type');

        $overtimeHours = (float) Overtime::query()
            ->where('status', 'approved')
            ->whereBetween('date', [$start, $end])
            ->sum('hours');

        $leaveDays = (float) Leave::query()
            ->where('status', 'approved')
            ->whereBetween('start_date', [$start, $end])
            ->sum('days');

        $statusText = [
            'normal' => '正常', 'late' => '迟到', 'early' => '早退', 'absent' => '缺勤', 'missing' => '缺卡',
        ];

        return view('hr::reports.attendance', [
            'month' => $month,
            'kpi' => [
                'total' => $total,
                'normal' => $normal,
                'rate' => $total > 0 ? round($normal / $total * 100, 1) : 0.0,
                'late' => (int) ($statusCounts['late'] ?? 0),
                'early' => (int) ($statusCounts['early'] ?? 0),
                'missing' => (int) (($statusCounts['missing'] ?? 0) + ($statusCounts['absent'] ?? 0)),
                'overtime' => $overtimeHours,
                'leave' => $leaveDays,
            ],
            'statusCounts' => $statusCounts,
            'statusText' => $statusText,
            'byDept' => $byDept,
            'exceptions' => $exceptions,
            'exceptionText' => ['late' => '迟到', 'early' => '早退', 'missing' => '缺卡', 'absent' => '缺勤', 'other' => '其它'],
        ]);
    }

    public function payroll(Request $request): View
    {
        $runs = PayrollRun::query()->orderByDesc('period')->get();
        $period = (string) $request->query('period', (string) ($runs->first()?->period ?? ''));

        $run = $runs->firstWhere('period', $period) ?? $runs->first();

        $items = $run
            ? PayrollItem::query()->where('run_id', $run->id)->get(['department_id', 'base', 'post', 'performance', 'allowance', 'overtime_pay', 'gross', 'net_pay', 'social_insurance', 'tax', 'leave_deduct'])
            : collect();

        $deptNames = DB::connection('hr')->table('departments')->pluck('name', 'id');

        $byDept = [];
        foreach ($items as $i) {
            $key = $deptNames[$i->department_id] ?? '未分配部门';
            $byDept[$key] ??= ['dept' => $key, 'people' => 0, 'gross' => 0.0, 'net' => 0.0, 'social' => 0.0, 'tax' => 0.0];
            $byDept[$key]['people']++;
            $byDept[$key]['gross'] += (float) $i->gross;
            $byDept[$key]['net'] += (float) $i->net_pay;
            $byDept[$key]['social'] += (float) $i->social_insurance;
            $byDept[$key]['tax'] += (float) $i->tax;
        }

        $rows = array_values($byDept);
        usort($rows, fn ($a, $b) => $b['gross'] <=> $a['gross']);
        foreach ($rows as $k => $r) {
            $rows[$k]['avg'] = $r['people'] > 0 ? round($r['gross'] / $r['people'], 2) : 0;
        }

        $sumGross = array_sum(array_column($rows, 'gross'));
        $sumNet = array_sum(array_column($rows, 'net'));
        $sumSocial = array_sum(array_column($rows, 'social'));
        $sumTax = array_sum(array_column($rows, 'tax'));
        $sumOt = (float) $items->sum('overtime_pay');

        // 环比：同口径对比上一批次的"应发"（批次表里的 total_amount 是实发，不能直接比）
        $prev = $runs->firstWhere('period', '!=', $run?->period);
        $prevGross = $prev
            ? (float) PayrollItem::query()->where('run_id', $prev->id)->sum('gross')
            : 0.0;
        $mom = $prevGross > 0 ? round(($sumGross - $prevGross) / $prevGross * 100, 1) : null;

        return view('hr::reports.payroll', [
            'runs' => $runs,
            'run' => $run,
            'period' => $run?->period ?? '',
            'rows' => $rows,
            'kpi' => [
                'people' => $items->count(),
                'gross' => round($sumGross, 2),
                'net' => round($sumNet, 2),
                'avg' => $items->count() > 0 ? round($sumGross / $items->count(), 2) : 0,
                'social' => round($sumSocial, 2),
                'tax' => round($sumTax, 2),
                'ot' => round($sumOt, 2),
                'social_rate' => $sumGross > 0 ? round($sumSocial / $sumGross * 100, 1) : 0,
                'tax_rate' => $sumGross > 0 ? round($sumTax / $sumGross * 100, 1) : 0,
                'mom' => $mom,
                'prev_period' => $prev?->period,
            ],
            'costStructure' => [
                ['label' => '基本 + 岗位', 'value' => round((float) $items->sum(fn ($i) => (float) $i->base + (float) $i->post), 2)],
                ['label' => '绩效 + 补贴', 'value' => round((float) $items->sum(fn ($i) => (float) $i->performance + (float) $i->allowance), 2)],
                ['label' => '加班费', 'value' => round($sumOt, 2)],
                ['label' => '社保', 'value' => round($sumSocial, 2)],
                ['label' => '个税', 'value' => round($sumTax, 2)],
            ],
        ]);
    }
}
