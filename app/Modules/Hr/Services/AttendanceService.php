<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\AttendanceException;
use App\Modules\Hr\Models\AttendanceRecord;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Shift;
use Carbon\Carbon;

/**
 * 打卡判定与考勤统计（H2-02）。
 *
 * 判定规则（演示口径，写死在服务里，页面不重复算）：
 *   - 缺上班卡或下班卡 → missing
 *   - 迟到分钟 = 实际上班 - (班次上班 + 迟到容忍)
 *   - 早退分钟 = 班次下班 - 实际下班（夜班跨天，下班日 +1）
 *   - 迟到与早退同时命中，取分钟数大的那个作为主状态
 *   - 迟到 > 30 分钟、早退 > 30 分钟、缺卡 → 自动生成一条 open 异常
 */
class AttendanceService
{
    /** 判定一条打卡。 */
    public function judge(?string $clockIn, ?string $clockOut, Shift $shift, string $workDate): array
    {
        if ($clockIn === null || $clockIn === '' || $clockOut === null || $clockOut === '') {
            return ['status' => 'missing', 'late_minutes' => 0, 'early_minutes' => 0];
        }

        $start = Carbon::parse($workDate . ' ' . $shift->work_start);
        $end = Carbon::parse($workDate . ' ' . $shift->work_end);
        if ((bool) $shift->cross_day) {
            $end->addDay();
        }

        $in = Carbon::parse($workDate . ' ' . $clockIn);
        // 夜班：打卡落在凌晨（如 00:30 才来上班），归属次日
        if ((bool) $shift->cross_day && $in->hour < 12 && (int) substr((string) $shift->work_start, 0, 2) >= 18) {
            $in->addDay();
        }

        $out = Carbon::parse($workDate . ' ' . $clockOut);
        if ((bool) $shift->cross_day && $out->lt($in)) {
            $out->addDay();
        }

        $lateMinutes = max(0, (int) floor(($in->timestamp - $start->timestamp) / 60) - (int) $shift->late_tolerance_min);
        $earlyMinutes = max(0, (int) floor(($end->timestamp - $out->timestamp) / 60));

        $status = 'normal';
        if ($lateMinutes > 0 && $lateMinutes >= $earlyMinutes) {
            $status = 'late';
        } elseif ($earlyMinutes > 0) {
            $status = 'early';
        }

        return [
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'early_minutes' => $earlyMinutes,
        ];
    }

    /**
     * 录入一条打卡（判定 + 按需生成异常）。
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Employee $employee, Shift $shift, string $workDate, ?string $clockIn, ?string $clockOut): AttendanceRecord
    {
        $judge = $this->judge($clockIn, $clockOut, $shift, $workDate);

        $record = AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'work_date' => $workDate,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'shift_id' => $shift->id,
            'status' => $judge['status'],
            'late_minutes' => $judge['late_minutes'],
            'early_minutes' => $judge['early_minutes'],
        ]);

        if ($judge['late_minutes'] > 30 || $judge['early_minutes'] > 30 || $judge['status'] === 'missing') {
            AttendanceException::query()->firstOrCreate(
                ['attendance_id' => $record->id],
                [
                    'employee_id' => $employee->id,
                    'type' => $judge['status'],
                    'work_date' => $workDate,
                    'reason' => $this->exceptionReason($judge),
                    'status' => 'open',
                ]
            );
        }

        return $record;
    }

    /**
     * 某月（或区间）的考勤统计。
     *
     * @return array<string, mixed>
     */
    public function stats(Carbon $from, Carbon $to, ?int $deptId = null): array
    {
        $base = AttendanceRecord::query()
            ->when($deptId > 0, fn ($q) => $q->whereHas('employee', fn ($inner) => $inner->where('department_id', $deptId)))
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]);

        $byStatus = (clone $base)
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $total = (int) $byStatus->sum();

        return [
            'total' => $total,
            'normal' => (int) ($byStatus['normal'] ?? 0),
            'late' => (int) ($byStatus['late'] ?? 0),
            'early' => (int) ($byStatus['early'] ?? 0),
            'missing' => (int) ($byStatus['missing'] ?? 0),
            'absent' => (int) ($byStatus['absent'] ?? 0),
            'people' => (int) (clone $base)->distinct()->count('employee_id'),
            'late_minutes' => (int) (clone $base)->sum('late_minutes'),
            'normal_rate' => $total > 0 ? round(((int) ($byStatus['normal'] ?? 0)) / $total * 100, 1) : 0,
        ];
    }

    /** 图表用的逐日正常率（只取近 10 个有记录的日期）。 */
    public function dailyTrend(Carbon $from, Carbon $to, ?int $deptId = null): array
    {
        $rows = AttendanceRecord::query()
            ->when($deptId > 0, fn ($q) => $q->whereHas('employee', fn ($inner) => $inner->where('department_id', $deptId)))
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("work_date, count(*) as total, sum(case when status = 'normal' then 1 else 0 end) as normal")
            ->groupBy('work_date')
            ->orderBy('work_date')
            ->limit(10)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $date = substr((string) $r->work_date, 0, 10);
            $out[] = [
                'date' => $date,
                'total' => (int) $r->total,
                'normal' => (int) $r->normal,
                'rate' => (int) $r->total > 0 ? round((int) $r->normal / (int) $r->total * 100, 1) : 0,
            ];
        }

        return $out;
    }

    /** @param  array<string, mixed>  $judge */
    private function exceptionReason(array $judge): string
    {
        return match ($judge['status']) {
            'missing' => '缺上班卡或下班卡',
            'late' => "迟到 {$judge['late_minutes']} 分钟",
            'early' => "早退 {$judge['early_minutes']} 分钟",
            default => '考勤异常',
        };
    }
}
