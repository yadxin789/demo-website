<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 年假额度核算（HR，M7 增补 3）。
 *
 * 对标 OrangeHRM Leave → Entitlements（按服务年限自动授予）：
 * 按司龄分段给年假天数，批量写入 leave_balances（type=annual），只改 total / remaining，
 * used 保持不动，避免把人已经休掉的天数冲掉。
 */
class LeaveAccrualService
{
    public const TYPE = 'annual';

    /**
     * 司龄规则：min 年（含）～ max 年（不含）→ 年假天数。
     *
     * @return array<int, array{min: float, max: ?float, days: float, label: string}>
     */
    public static function rules(): array
    {
        return [
            ['min' => 0, 'max' => 1, 'days' => 0, 'label' => '不满 1 年（无年假）'],
            ['min' => 1, 'max' => 3, 'days' => 5, 'label' => '满 1 年不满 3 年'],
            ['min' => 3, 'max' => 5, 'days' => 7, 'label' => '满 3 年不满 5 年'],
            ['min' => 5, 'max' => 10, 'days' => 10, 'label' => '满 5 年不满 10 年'],
            ['min' => 10, 'max' => null, 'days' => 15, 'label' => '满 10 年以上'],
        ];
    }

    /** 单个员工的应享年假：按入职日到当年 12-31 的司龄算 */
    public function entitlement(?Carbon $hireDate, ?Carbon $refDate = null): array
    {
        $ref = ($refDate ?? now())->copy();
        if ($hireDate === null) {
            return ['days' => 0.0, 'label' => '无入职日期', 'years' => 0.0];
        }

        $years = round($hireDate->diffInDays($ref) / 365.25, 2);

        foreach (self::rules() as $rule) {
            $inMin = $years >= $rule['min'];
            $inMax = $rule['max'] === null || $years < $rule['max'];
            if ($inMin && $inMax) {
                return ['days' => (float) $rule['days'], 'label' => $rule['label'], 'years' => $years];
            }
        }

        return ['days' => 0.0, 'label' => '未匹配规则', 'years' => $years];
    }

    /**
     * 预览：在职员工逐一算应享与现有额度，给出差异。
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function preview(?int $year = null): Collection
    {
        $year = $year ?? (int) now()->format('Y');
        $cutoff = Carbon::create($year, 12, 31)->endOfDay();

        $employees = Employee::query()
            ->with('department:id,name')
            ->whereIn('status', ['active', 'probation'])
            ->orderBy('employee_no')
            ->get();

        $current = LeaveBalance::query()
            ->where('year', $year)
            ->where('type', self::TYPE)
            ->get()
            ->keyBy('employee_id');

        return $employees->map(function (Employee $e) use ($current, $cutoff) {
            $ent = $this->entitlement($e->hire_date ? Carbon::parse($e->hire_date) : null, $cutoff);
            $bal = $current->get($e->id);
            $used = $bal ? (float) $bal->used : 0.0;
            $total = $bal ? (float) $bal->total : 0.0;

            return [
                'employee' => $e,
                'hire_date' => $e->hire_date,
                'years' => $ent['years'],
                'label' => $ent['label'],
                'entitled' => $ent['days'],
                'current_total' => $total,
                'used' => $used,
                'delta' => round($ent['days'] - $total, 2),
                'has_balance' => $bal !== null,
            ];
        });
    }

    /**
     * 执行核算：把应享天数写进 leave_balances。
     *
     * @return array{updated:int, created:int, unchanged:int, total_days:float}
     */
    public function run(?int $year = null): array
    {
        $year = $year ?? (int) now()->format('Y');
        $rows = $this->preview($year);

        $stat = ['updated' => 0, 'created' => 0, 'unchanged' => 0, 'total_days' => 0.0];

        foreach ($rows as $row) {
            /** @var Employee $e */
            $e = $row['employee'];
            $entitled = (float) $row['entitled'];
            $used = (float) $row['used'];
            $remaining = max(0.0, $entitled - $used);

            $existing = LeaveBalance::query()
                ->where('employee_id', $e->id)
                ->where('year', $year)
                ->where('type', self::TYPE)
                ->first();

            if ($existing === null) {
                LeaveBalance::query()->create([
                    'employee_id' => $e->id,
                    'year' => $year,
                    'type' => self::TYPE,
                    'total' => $entitled,
                    'used' => $used,
                    'remaining' => $remaining,
                ]);
                $stat['created']++;
            } elseif (abs((float) $existing->total - $entitled) < 0.001) {
                $stat['unchanged']++;
            } else {
                $existing->update(['total' => $entitled, 'remaining' => $remaining]);
                $stat['updated']++;
            }

            $stat['total_days'] += $entitled;
        }

        return $stat;
    }
}
