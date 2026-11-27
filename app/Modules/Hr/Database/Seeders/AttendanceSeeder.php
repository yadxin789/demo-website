<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 考勤种子（H3）：近 22 个工作日 × 在职员工 ≈ 1300 条打卡，含迟到 / 早退 / 缺卡。
 *
 * 异常表只收"需要人处理"的记录：15 条待处理 + 35 条已处理（规划书 §6.2）。
 * 口径与 AttendanceService 一致：迟到看上班时间 + 容忍分钟，早退看下班时间。
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // 清本域
        foreach (['attendance_exceptions', 'attendance_records', 'overtimes'] as $t) {
            DB::connection('hr')->table($t)->delete();
        }

        $shift = Shift::query()->where('code', 'S01')->first()
            ?? Shift::query()->orderBy('id')->first();

        if (! $shift) {
            $this->command?->warn('没有班次，跳过考勤种子');
            return;
        }

        $emps = Employee::query()->whereIn('status', ['active', 'probation'])
            ->orderBy('employee_no')
            ->get(['id', 'employee_no', 'name', 'status', 'department_id']);

        // 近 30 天里的工作日（周一~周五），取最近 22 个
        $days = [];
        $cursor = now()->copy()->startOfDay();
        while (count($days) < 22 && $cursor->greaterThan(now()->copy()->subDays(45))) {
            if ($cursor->isWeekday()) {
                $days[] = $cursor->copy();
            }
            $cursor->subDay();
        }
        sort($days);

        [$startH, $startM] = array_map('intval', explode(':', (string) $shift->work_start));
        [$endH, $endM] = array_map('intval', explode(':', (string) $shift->work_end));
        $startMin = $startH * 60 + $startM;
        $endMin = $endH * 60 + $endM;
        $tolerance = (int) $shift->late_tolerance_min;

        $records = [];
        $seq = 0;

        foreach ($emps as $e) {
            // 每 5 个工作日留 1 天不出勤（加班倒休等），避免满勤数据太假
            foreach ($days as $k => $d) {
                if (($seq + $k) % 5 === 0) {
                    continue;
                }

                $inOffset = random_int(-6, 8);          // 大多数人准点或早到
                $inMin = $startMin + $inOffset;
                $outMin = $endMin + random_int(-5, 30);

                $records[] = [
                    'employee_id' => $e->id,
                    'work_date' => $d->toDateString(),
                    'clock_in' => sprintf('%02d:%02d', intdiv($inMin, 60), $inMin % 60),
                    'clock_out' => sprintf('%02d:%02d', intdiv($outMin, 60), $outMin % 60),
                    'shift_id' => $shift->id,
                    'status' => 'normal',
                    'late_minutes' => 0,
                    'early_minutes' => 0,
                    'remark' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $seq++;
        }

        // 抽取迟到 30 / 早退 12 / 缺卡 8（互不重复）
        $pool = array_keys($records);
        shuffle($pool);
        $late = array_splice($pool, 0, 30);
        $early = array_splice($pool, 0, 12);
        $missing = array_splice($pool, 0, 8);

        foreach ($late as $i) {
            $add = random_int($tolerance + 3, 35);
            $inMin = $startMin + $add;
            $records[$i]['clock_in'] = sprintf('%02d:%02d', intdiv($inMin, 60), $inMin % 60);
            $records[$i]['status'] = 'late';
            $records[$i]['late_minutes'] = $add;
        }

        foreach ($early as $i) {
            $outMin = $endMin - random_int(10, 70);
            $records[$i]['clock_out'] = sprintf('%02d:%02d', intdiv($outMin, 60), $outMin % 60);
            $records[$i]['status'] = 'early';
            $records[$i]['early_minutes'] = $endMin - $outMin;
        }

        foreach ($missing as $i) {
            $records[$i]['clock_out'] = null;
            $records[$i]['status'] = 'missing';
        }

        foreach (array_chunk($records, 300) as $chunk) {
            DB::connection('hr')->table('attendance_records')->insert($chunk);
        }

        // 异常：三类各生成一条，前 15 条待处理
        $rows = DB::connection('hr')->table('attendance_records')
            ->whereIn('status', ['late', 'early', 'missing'])
            ->orderBy('work_date')
            ->get();

        $exceptions = [];
        $typeText = ['late' => '迟到', 'early' => '早退', 'missing' => '缺卡'];
        $exSeq = 0;
        foreach ($rows as $r) {
            $exSeq++;
            $open = $exSeq <= 15;
            $exceptions[] = [
                'attendance_id' => $r->id,
                'employee_id' => $r->employee_id,
                'type' => $r->status,
                'work_date' => $r->work_date,
                'reason' => $open ? null : ($typeText[$r->status] ?? '考勤异常') . '，已核实',
                'status' => $open ? 'open' : 'handled',
                'result' => $open ? null : ($r->status === 'missing' ? '补卡' : '不计'),
                'handler_id' => null,
                'handled_at' => $open ? null : now()->copy()->subDays(random_int(1, 5)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($exceptions, 200) as $chunk) {
            DB::connection('hr')->table('attendance_exceptions')->insert($chunk);
        }

        // 加班 40 条：工作日 30 / 周末 10，已批 30 / 待批 10
        $overtimes = [];
        for ($i = 1; $i <= 40; $i++) {
            $e = $emps[($i * 3) % $emps->count()];
            $isWeekend = $i % 4 === 0;
            $date = $isWeekend
                ? now()->copy()->subDays(random_int(1, 6))->next('Saturday')->subDays(random_int(0, 60))
                : now()->copy()->subDays(random_int(1, 30));
            $approved = $i > 10;

            $overtimes[] = [
                'employee_id' => $e->id,
                'date' => $date->toDateString(),
                'hours' => random_int(2, 8) / 2,
                'type' => $isWeekend ? 'weekend' : 'workday',
                'reason' => $isWeekend ? '周末产线支援' : '月度结账加班',
                'status' => $approved ? 'approved' : 'pending',
                'approver_id' => null,
                'approved_at' => $approved ? $date->copy()->addDay() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::connection('hr')->table('overtimes')->insert($overtimes);

        $this->command?->info('打卡 ' . count($records) . ' 条 / 异常 ' . count($exceptions) . ' 条 / 加班 40 条');
    }
}
