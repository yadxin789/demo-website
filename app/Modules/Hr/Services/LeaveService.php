<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Leave;
use App\Modules\Hr\Models\LeaveBalance;
use Carbon\Carbon;

/**
 * 请假申请与审批（H2-08）。
 *
 * 规则：
 *   - 请假编号 LV-{年}-{4 位流水}
 *   - 天数按自然日含首尾：同日 0.5 天（半天假），跨日 = 日差 + 1
 *   - 批准：只对年假（annual）/调休（compensatory）扣余额；余额不足时扣到 0 并给出提示
 *   - 驳回：必须写原因，不动余额
 */
class LeaveService
{
    /** 下一个请假编号。 */
    public function nextLeaveNo(): string
    {
        $year = now()->format('Y');
        $prefix = 'LV-' . $year . '-';

        $max = Leave::query()
            ->where('leave_no', 'like', $prefix . '%')
            ->selectRaw('max(leave_no) as no')
            ->value('no');

        $seq = $max ? ((int) substr((string) $max, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** 请假天数（含首尾；同日可 0.5）。 */
    public function daysBetween(string $start, string $end, bool $halfDay = false): float
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();

        if ($s->equalTo($e) && $halfDay) {
            return 0.5;
        }

        return max(0.5, (float) ($s->diffInDays($e) + 1));
    }

    /**
     * 提交申请。
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(Employee $employee, array $data): Leave
    {
        $days = $this->daysBetween($data['start_date'], $data['end_date'], (bool) ($data['half_day'] ?? false));

        return Leave::query()->create([
            'leave_no' => $this->nextLeaveNo(),
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);
    }

    /**
     * 批准：扣余额（年假 / 调休）。
     *
     * @return array{leave: Leave, deducted: float, balance_left: float|null}
     */
    public function approve(Leave $leave): array
    {
        $deducted = 0.0;
        $balanceLeft = null;

        if (in_array($leave->type, ['annual', 'compensatory'], true)) {
            $balance = LeaveBalance::query()
                ->where('employee_id', $leave->employee_id)
                ->where('year', (int) $this->yearOf($leave->start_date))
                ->where('type', $leave->type)
                ->first();

            if ($balance) {
                $days = (float) $leave->days;
                $deducted = min($days, (float) $balance->remaining);
                $balance->update([
                    'used' => (float) $balance->used + $deducted,
                    'remaining' => max(0, (float) $balance->remaining - $days),
                ]);
                $balanceLeft = (float) $balance->remaining;
            }
        }

        $leave->update([
            'status' => 'approved',
            'approver_id' => auth('hr')->id(),
            'approved_at' => now(),
        ]);

        return ['leave' => $leave, 'deducted' => $deducted, 'balance_left' => $balanceLeft];
    }

    /** 驳回。 */
    public function reject(Leave $leave, string $reason): Leave
    {
        $leave->update([
            'status' => 'rejected',
            'approver_id' => auth('hr')->id(),
            'approved_at' => now(),
            'reject_reason' => $reason,
        ]);

        return $leave;
    }

    private function yearOf(?object $date): string
    {
        return $date?->format('Y') ?? now()->format('Y');
    }

    /** @return array<string, string> */
    public static function typeText(): array
    {
        return [
            'annual' => '年假',
            'sick' => '病假',
            'personal' => '事假',
            'compensatory' => '调休',
        ];
    }

    /** @return array<string, string> */
    public static function statusText(): array
    {
        return [
            'pending' => '待审批',
            'approved' => '已批准',
            'rejected' => '已驳回',
        ];
    }
}
