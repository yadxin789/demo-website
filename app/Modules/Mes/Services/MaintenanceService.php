<?php

declare(strict_types=1);

namespace App\Modules\Mes\Services;

use App\Modules\Mes\Models\MaintenanceOrder;
use App\Modules\Mes\Models\MaintenancePlan;
use Illuminate\Support\Facades\DB;

/**
 * 设备保养（MES，M7 增补）。
 *
 * 对标 Odoo Maintenance：计划（preventive）→ 到期生成保养工单 → 完成后回写计划的下次到期日。
 * 故障维修（cm）由设备报修转入，同样走 maintenance_orders，保证"计划保养 + 故障维修"一张表看全。
 */
class MaintenanceService
{
    /** 到期计划一键生成保养工单，返回生成条数 */
    public function generateDueOrders(): int
    {
        $count = 0;

        DB::connection('mes')->transaction(function () use (&$count) {
            $plans = MaintenancePlan::query()
                ->where('is_active', true)
                ->whereNotNull('next_due_at')
                ->whereDate('next_due_at', '<=', now()->toDateString())
                ->get();

            foreach ($plans as $plan) {
                // 同一计划已有未完成的保养单就不重复生成
                $exists = MaintenanceOrder::query()
                    ->where('plan_id', $plan->id)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                MaintenanceOrder::query()->create([
                    'order_no' => $this->nextOrderNo(),
                    'plan_id' => $plan->id,
                    'equipment_id' => $plan->equipment_id,
                    'type' => 'pm',
                    'status' => 'pending',
                    'due_at' => $plan->next_due_at,
                    'remark' => "按计划 {$plan->code} 到期生成",
                ]);
                $count++;
            }
        });

        return $count;
    }

    /** 完成保养：回写计划的下次到期日 */
    public function complete(MaintenanceOrder $order, float $costHours, ?string $remark): void
    {
        DB::connection('mes')->transaction(function () use ($order, $costHours, $remark) {
            $order->update([
                'status' => 'done',
                'done_at' => now(),
                'cost_hours' => $costHours,
                'remark' => $remark ?? $order->remark,
            ]);

            if ($order->plan) {
                $order->plan->update([
                    'last_done_at' => now()->toDateString(),
                    'next_due_at' => now()->addDays((int) $order->plan->cycle_days)->toDateString(),
                ]);
            }
        });
    }

    /** 保存计划时把 next_due_at 推算出来 */
    public function savePlan(array $data, ?MaintenancePlan $plan = null): MaintenancePlan
    {
        $cycle = (int) ($data['cycle_days'] ?? 30);
        $lastDone = $data['last_done_at'] ?? null;

        $nextDue = $data['next_due_at'] ?? null;
        if (! $nextDue) {
            $base = $lastDone ? \Illuminate\Support\Carbon::parse($lastDone) : now();
            $nextDue = $base->copy()->addDays($cycle)->toDateString();
        }

        $payload = [
            'code' => $data['code'],
            'name' => $data['name'],
            'equipment_id' => (int) $data['equipment_id'],
            'cycle_days' => $cycle,
            'standard_minutes' => (int) ($data['standard_minutes'] ?? 60),
            'last_done_at' => $lastDone,
            'next_due_at' => $nextDue,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'remark' => $data['remark'] ?? null,
        ];

        if ($plan) {
            $plan->update($payload);

            return $plan;
        }

        return MaintenancePlan::query()->create($payload);
    }

    public function nextOrderNo(): string
    {
        $prefix = 'MO-' . now()->format('Y') . '-';
        $last = MaintenanceOrder::query()->where('order_no', 'like', $prefix . '%')->orderByDesc('order_no')->value('order_no');
        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function nextPlanCode(): string
    {
        $last = MaintenancePlan::query()->orderByDesc('id')->value('code');
        $seq = $last ? ((int) preg_replace('/\D/', '', (string) $last)) + 1 : 1;

        return 'MP-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
