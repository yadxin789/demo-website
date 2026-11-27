<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Leave;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Models\PayrollItem;
use App\Modules\Hr\Models\PayrollRun;
use App\Modules\Hr\Models\SalaryStructure;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 工资核算（H3-02）。
 *
 * 口径与 `PayrollSeeder` 完全一致，保证"重新生成"与演示数据能对上：
 *   绩效     = 岗位绩效档 × 系数（试用 0.8，其余 0.9 / 0.95 / 1.0 / 1.05 / 1.1 按工号错开）
 *   加班费   = 基本工资 ÷ 174 × 1.5 × 当月已批准加班小时
 *   请假扣款 = (基本 + 岗位) ÷ 21.75 × 当月已批准请假天数
 *   应发     = 基本 + 岗位 + 绩效 + 补贴 + 加班费
 *   社保     = 应发 × 10.5%
 *   个税     = max(0, (应发 − 社保 − 5000) × 3%)
 *   实发     = 应发 − 请假扣款 − 社保 − 个税
 */
class PayrollService
{
    private const HOURS_PER_MONTH = 174;

    private const DAYS_PER_MONTH = 21.75;

    private const OVERTIME_FACTOR = 1.5;

    private const SOCIAL_RATE = 0.105;

    private const TAX_RATE = 0.03;

    private const TAX_THRESHOLD = 5000;

    /** 当前应发月份（YYYY-MM）。 */
    public function periodNow(): string
    {
        return now()->format('Y-m');
    }

    /** 工资单号 PAY-YYYYMM。 */
    public function payrollNo(string $period): string
    {
        return 'PAY-' . str_replace('-', '', $period);
    }

    /**
     * 生成某月工资批次（同一月份只允许一批）。
     */
    public function generate(string $period): PayrollRun
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new RuntimeException('月份格式应为 YYYY-MM。');
        }

        if (PayrollRun::query()->where('period', $period)->exists()) {
            throw new RuntimeException("{$period} 的工资批次已经存在，不能重复生成。");
        }

        $from = Carbon::parse($period . '-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $periodStart = $from->toDateString();
        $periodEnd = $to->toDateString();

        $employees = Employee::query()
            ->with(['position:id,code,name'])
            ->whereIn('status', ['active', 'probation'])
            ->where('hire_date', '<=', $periodEnd)
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('leave_date')->orWhere('leave_date', '>=', $periodStart);
            })
            ->orderBy('employee_no')
            ->get();

        if ($employees->isEmpty()) {
            throw new RuntimeException('这个月没有在职员工，无法生成工资单。');
        }

        // 当月已批准加班小时
        $otHours = Overtime::query()
            ->where('status', 'approved')
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->selectRaw('employee_id, sum(hours) as h')
            ->groupBy('employee_id')
            ->pluck('h', 'employee_id');

        // 当月已批准请假天数
        $leaveDays = Leave::query()
            ->where('status', 'approved')
            ->whereBetween('start_date', [$periodStart, $periodEnd])
            ->selectRaw('employee_id, sum(days) as d')
            ->groupBy('employee_id')
            ->pluck('d', 'employee_id');

        // 岗位薪资档：取生效日不晚于月末的最新一条
        $structures = SalaryStructure::query()
            ->where('effective_date', '<=', $periodEnd)
            ->orderBy('effective_date')
            ->get()
            ->keyBy('position_id');

        return DB::connection('hr')->transaction(function () use ($employees, $otHours, $leaveDays, $structures, $period, $periodStart, $periodEnd) {
            $run = PayrollRun::query()->create([
                'period' => $period,
                'payroll_no' => $this->payrollNo($period),
                'status' => 'draft',
                'employee_count' => 0,
                'total_amount' => 0,
                'generated_at' => now(),
            ]);

            $total = 0.0;
            $count = 0;
            $rows = [];

            foreach ($employees as $employee) {
                $structure = $structures[$employee->position_id] ?? null;
                if (! $structure) {
                    continue; // 岗位没有薪资档，跳过（不写全零工资条）
                }

                $base = (float) $structure->base;
                $post = (float) $structure->post;
                $perfStd = (float) $structure->performance;
                $allow = (float) $structure->allowance;

                $factor = $employee->status === 'probation' ? 0.8 : 0.9 + (($employee->id % 5) * 0.05);
                $perf = round($perfStd * $factor, 2);

                $hours = (float) ($otHours[$employee->id] ?? 0);
                $otPay = round($base / self::HOURS_PER_MONTH * self::OVERTIME_FACTOR * $hours, 2);

                $days = (float) ($leaveDays[$employee->id] ?? 0);
                $deduct = round(($base + $post) / self::DAYS_PER_MONTH * $days, 2);

                $gross = round($base + $post + $perf + $allow + $otPay, 2);
                $social = round($gross * self::SOCIAL_RATE, 2);
                $tax = round(max(0, ($gross - $social - self::TAX_THRESHOLD) * self::TAX_RATE), 2);
                $net = round($gross - $deduct - $social - $tax, 2);

                $rows[] = [
                    'run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'employee_no' => $employee->employee_no,
                    'employee_name' => $employee->name,
                    'department_id' => $employee->department_id,
                    'base' => $base,
                    'post' => $post,
                    'performance' => $perf,
                    'allowance' => $allow,
                    'overtime_pay' => $otPay,
                    'leave_deduct' => $deduct,
                    'social_insurance' => $social,
                    'tax' => $tax,
                    'gross' => $gross,
                    'net_pay' => $net,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $total += $net;
                $count++;
            }

            PayrollItem::query()->insert($rows);

            $run->update([
                'employee_count' => $count,
                'total_amount' => round($total, 2),
            ]);

            return $run;
        });
    }

    /** 草稿 → 确认。 */
    public function confirm(PayrollRun $run): PayrollRun
    {
        if ($run->status !== 'draft') {
            throw new RuntimeException('只有草稿状态的批次可以确认。');
        }

        $run->update(['status' => 'confirmed']);

        return $run;
    }

    /** 确认 → 已发放。 */
    public function pay(PayrollRun $run): PayrollRun
    {
        if ($run->status !== 'confirmed') {
            throw new RuntimeException('只有已确认的批次可以标记发放。');
        }

        $run->update(['status' => 'paid', 'paid_at' => now()]);

        return $run;
    }

    /**
     * 批次汇总（合计 / 人均 / 各分项合计）。
     *
     * @return array<string, mixed>
     */
    public function summary(PayrollRun $run): array
    {
        $items = PayrollItem::query()->where('run_id', $run->id);

        $sum = (clone $items)->selectRaw('
            count(*) as n,
            sum(gross) as gross,
            sum(net_pay) as net,
            sum(overtime_pay) as ot,
            sum(leave_deduct) as deduct,
            sum(social_insurance) as social,
            sum(tax) as tax
        ')->first();

        $n = (int) ($sum->n ?? 0);

        return [
            'count' => $n,
            'gross' => round((float) ($sum->gross ?? 0), 2),
            'net' => round((float) ($sum->net ?? 0), 2),
            'overtime' => round((float) ($sum->ot ?? 0), 2),
            'deduct' => round((float) ($sum->deduct ?? 0), 2),
            'social' => round((float) ($sum->social ?? 0), 2),
            'tax' => round((float) ($sum->tax ?? 0), 2),
            'avg_net' => $n > 0 ? round((float) $sum->net / $n, 2) : 0.0,
        ];
    }

    /**
     * 本人工资条（按工号匹配，只返回本人；草稿批次不给员工看）。
     *
     * @return Collection<int, PayrollItem>
     */
    public function myItems(string $employeeNo, int $limit = 6): Collection
    {
        $visibleRuns = PayrollRun::query()
            ->whereIn('status', ['confirmed', 'paid'])
            ->orderByDesc('period')
            ->limit($limit)
            ->pluck('id');

        if ($visibleRuns->isEmpty()) {
            return collect();
        }

        return PayrollItem::query()
            ->where('employee_no', $employeeNo)
            ->whereIn('run_id', $visibleRuns)
            ->orderByDesc('run_id')
            ->get();
    }
}
