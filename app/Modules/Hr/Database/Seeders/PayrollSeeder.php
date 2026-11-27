<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\PayrollRun;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\SalaryStructure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 薪资种子（H4）：两个批次 —— 2026-10 已发放、2026-11 草稿。
 *
 * 简化口径（演示版，不做真社保与个税）：
 *   应发 gross   = 基本 + 岗位 + 绩效 + 补贴 + 加班费
 *   实发 net_pay = 应发 − 请假扣款 − 社保 − 个税
 *   加班费 = 平时 1.5 倍折算，月加班小时按 overtime 汇总
 *   社保 = 应发 × 10.5%，个税 = max(0, (应发 − 5000) × 3%)
 */
class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['payroll_items', 'payroll_runs'] as $t) {
            DB::connection('hr')->table($t)->delete();
        }

        $emps = Employee::query()->whereIn('status', ['active', 'probation'])
            ->orderBy('employee_no')
            ->get(['id', 'employee_no', 'name', 'department_id', 'position_id', 'status']);

        if ($emps->isEmpty()) {
            return;
        }

        $structures = SalaryStructure::query()->get()->keyBy('position_id');
        $positions = Position::query()->get()->keyBy('id');

        // 加班小时（按人汇总近两月）
        $otRows = DB::connection('hr')->table('overtimes')
            ->where('status', 'approved')
            ->where('date', '>=', now()->copy()->subDays(60)->toDateString())
            ->selectRaw('employee_id, sum(hours) as h')
            ->get();
        $otByEmp = [];
        foreach ($otRows as $r) {
            $otByEmp[$r->employee_id] = (float) $r->h;
        }

        // 请假扣款（按已批年假/事假的天数折算日薪）
        $leaveRows = DB::connection('hr')->table('leaves')
            ->whereIn('status', ['approved'])
            ->whereIn('type', ['personal', 'annual'])
            ->where('start_date', '>=', now()->copy()->subDays(60)->toDateString())
            ->selectRaw('employee_id, sum(days) as d')
            ->get();
        $leaveByEmp = [];
        foreach ($leaveRows as $r) {
            $leaveByEmp[$r->employee_id] = (float) $r->d;
        }

        foreach ([
            ['2026-10', 'PAY-202610', 'paid', now()->copy()->subMonths(1)->endOfMonth()],
            ['2026-11', 'PAY-202611', 'draft', null],
        ] as $i => [$period, $no, $status, $paidAt]) {
            $run = PayrollRun::query()->updateOrCreate(
                ['period' => $period],
                [
                    'payroll_no' => $no,
                    'status' => $status,
                    'employee_count' => 0,
                    'total_amount' => 0,
                    'operator_id' => null,
                    'generated_at' => now()->copy()->subDays(10 + $i),
                    'paid_at' => $paidAt,
                ]
            );

            $items = [];
            $total = 0.0;

            foreach ($emps as $e) {
                $st = $structures[$e->position_id] ?? null;
                $base = (float) ($st->base ?? 3500);
                $post = (float) ($st->post ?? 1200);
                $perf = (float) ($st->performance ?? 800);
                $allow = (float) ($st->allowance ?? 500);

                // 绩效系数：试用期 0.8，其他人 0.9–1.1
                $factor = $e->status === 'probation' ? 0.8 : (0.9 + (($e->id % 5) * 0.05));
                // 上一批次（已发放）按 0.95 折算，模拟"上月绩效偏低"，
                // 这样两个批次的金额有真实差异，人力成本页的环比不会恒为 0%
                if ($i === 0) {
                    $factor = round($factor * 0.95, 4);
                }
                $perf = round($perf * $factor, 2);

                $hourly = $base / 174;
                $otHours = $otByEmp[$e->id] ?? 0;
                $otPay = round($hourly * 1.5 * $otHours, 2);

                $leaveDays = $leaveByEmp[$e->id] ?? 0;
                $dayPay = ($base + $post) / 21.75;
                $deduct = round($dayPay * $leaveDays, 2);

                $gross = $base + $post + $perf + $allow + $otPay;
                $social = round($gross * 0.105, 2);
                $tax = round(max(0, ($gross - $social - 5000) * 0.03), 2);
                $net = round($gross - $deduct - $social - $tax, 2);

                $items[] = [
                    'run_id' => $run->id,
                    'employee_id' => $e->id,
                    'employee_no' => $e->employee_no,
                    'employee_name' => $e->name,
                    'department_id' => $e->department_id,
                    'base' => $base,
                    'post' => $post,
                    'performance' => $perf,
                    'allowance' => $allow,
                    'overtime_pay' => $otPay,
                    'leave_deduct' => $deduct,
                    'social_insurance' => $social,
                    'tax' => $tax,
                    'gross' => round($gross, 2),
                    'net_pay' => $net,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $total += $net;
            }

            foreach (array_chunk($items, 300) as $chunk) {
                DB::connection('hr')->table('payroll_items')->insert($chunk);
            }

            $run->update([
                'employee_count' => count($items),
                'total_amount' => round($total, 2),
            ]);
        }

        $this->command?->info('工资批次 2 个 / 明细 ' . (count($emps) * 2) . ' 条');
    }
}
