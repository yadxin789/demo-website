<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Leave;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 请假与假期余额种子（H3 下半）。
 *
 * 35 条请假：待审 5 / 已批 25 / 驳回 5；类型覆盖年假 / 病假 / 事假 / 调休。
 * 余额：每人年假 + 调休两条，已用天数按已批年假累计。
 */
class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['leaves', 'leave_balances'] as $t) {
            DB::connection('hr')->table($t)->delete();
        }

        $emps = Employee::query()->whereIn('status', ['active', 'probation'])
            ->orderBy('employee_no')
            ->get(['id', 'employee_no', 'name', 'hire_date']);

        if ($emps->isEmpty()) {
            return;
        }

        $types = ['annual', 'sick', 'personal', 'compensatory'];
        $leaves = [];
        $usedByEmp = [];

        for ($i = 1; $i <= 35; $i++) {
            $e = $emps[($i * 5) % $emps->count()];
            $type = $types[$i % 4];
            $days = [0.5, 1, 1, 2, 2, 3, 5][$i % 7];

            $start = now()->copy()->subDays(random_int(-10, 120));
            $end = $start->copy()->addDays((int) ceil($days) - 1);

            // 待审 5 条放在近几天，客户一眼能看到待办
            $status = 'approved';
            if ($i <= 5) {
                $status = 'pending';
                $start = now()->copy()->addDays(random_int(1, 8));
                $end = $start->copy()->addDays((int) ceil($days) - 1);
            } elseif ($i % 7 === 0) {
                $status = 'rejected';
            }

            if ($status === 'approved' && in_array($type, ['annual', 'compensatory'], true)) {
                $usedByEmp[$e->id][$type] = ($usedByEmp[$e->id][$type] ?? 0) + $days;
            }

            $leaves[] = [
                'leave_no' => 'LV-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'employee_id' => $e->id,
                'type' => $type,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'reason' => ['annual' => '年假出行', 'sick' => '感冒发热', 'personal' => '家里有事', 'compensatory' => '调休'][$type],
                'status' => $status,
                'approver_id' => null,
                'approved_at' => $status === 'pending' ? null : $start->copy()->subDays(2),
                'reject_reason' => $status === 'rejected' ? '当月人手紧张，改期再休' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($leaves, 200) as $chunk) {
            DB::connection('hr')->table('leaves')->insert($chunk);
        }

        // 余额：年假 total 5–15；调休 total 3–6
        $balances = [];
        foreach ($emps as $idx => $e) {
            $annualTotal = 5 + ($idx % 11);
            $annualUsed = min($annualTotal, (float) ($usedByEmp[$e->id]['annual'] ?? 0));

            $balances[] = [
                'employee_id' => $e->id,
                'year' => (int) now()->format('Y'),
                'type' => 'annual',
                'total' => $annualTotal,
                'used' => $annualUsed,
                'remaining' => $annualTotal - $annualUsed,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $compTotal = 3 + ($idx % 4);
            $compUsed = min($compTotal, (float) ($usedByEmp[$e->id]['compensatory'] ?? 0));

            $balances[] = [
                'employee_id' => $e->id,
                'year' => (int) now()->format('Y'),
                'type' => 'compensatory',
                'total' => $compTotal,
                'used' => $compUsed,
                'remaining' => $compTotal - $compUsed,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($balances, 300) as $chunk) {
            DB::connection('hr')->table('leave_balances')->insert($chunk);
        }

        $this->command?->info('请假 ' . count($leaves) . ' 条 / 余额 ' . count($balances) . ' 行 / 待审 ' . Leave::query()->where('status', 'pending')->count());
    }
}
