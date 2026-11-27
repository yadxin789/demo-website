<?php

declare(strict_types=1);

namespace App\Modules\Mes\Database\Seeders;

use App\Modules\Mes\Models\Equipment;
use App\Modules\Mes\Models\FinishedReceipt;
use App\Modules\Mes\Models\Lot;
use App\Modules\Mes\Models\MaintenanceOrder;
use App\Modules\Mes\Models\MaintenancePlan;
use App\Modules\Mes\Models\Material;
use App\Modules\Mes\Models\WorkOrder;
use Illuminate\Database\Seeder;

/**
 * 完工入库 / 批次 / 保养计划演示数据（M7 增补）。
 *
 * 幂等：用 updateOrCreate 按业务键（批次号 / 计划编号 / 保养单号）写入，重复跑不会翻倍。
 */
class CompletionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRawLots();
        $this->seedFinishedReceipts();
        $this->seedMaintenancePlans();
    }

    /** 原料批次：给前 12 个原料各建一个采购批次 */
    private function seedRawLots(): void
    {
        $materials = Material::query()->orderBy('id')->limit(12)->get();

        foreach ($materials as $i => $m) {
            $lotNo = 'LOT-P202610-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

            Lot::query()->updateOrCreate(
                ['lot_no' => $lotNo],
                [
                    'material_id' => $m->id,
                    'source' => 'purchase',
                    'work_order_id' => null,
                    'qty' => 500,
                    'parent_lot_no' => null,
                    'produced_at' => now()->subDays(20)->toDateString(),
                    'remark' => '演示数据：采购入库批次',
                ]
            );
        }
    }

    /** 已完工工单补入库单，并挂上原料批次形成追溯链 */
    private function seedFinishedReceipts(): void
    {
        $orders = WorkOrder::query()->where('status', 'completed')->orderBy('id')->get();
        $rawLots = Lot::query()->where('source', 'purchase')->orderBy('id')->get();

        $seq = 0;
        foreach ($orders as $o) {
            $seq++;
            $lotNo = 'LOT-202611-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $qty = (float) $o->plan_qty;

            // 上游批次：按工单 id 取一个原料批次，模拟"这批成品用了这批料"
            $parent = $rawLots->count() > 0 ? $rawLots[($seq - 1) % $rawLots->count()]->lot_no : null;

            FinishedReceipt::query()->updateOrCreate(
                ['receipt_no' => 'FR-2026-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT)],
                [
                    'work_order_id' => $o->id,
                    'material_id' => $o->product_material_id,
                    'lot_no' => $lotNo,
                    'qty' => $qty,
                    'warehouse' => '成品库',
                    'received_by' => null,
                    'received_at' => now()->subDays($seq)->setTime(16, 20),
                    'remark' => '演示数据：工单完工入库',
                ]
            );

            Lot::query()->updateOrCreate(
                ['lot_no' => $lotNo],
                [
                    'material_id' => $o->product_material_id,
                    'source' => 'production',
                    'work_order_id' => $o->id,
                    'qty' => $qty,
                    'parent_lot_no' => $parent,
                    'produced_at' => now()->subDays($seq)->toDateString(),
                    'remark' => '演示数据：成品批次',
                ]
            );
        }
    }

    /** 保养计划 + 保养单：6 台设备，其中 2 台已到期 */
    private function seedMaintenancePlans(): void
    {
        $equipments = Equipment::query()->orderBy('code')->limit(6)->get();
        $planNames = ['月度润滑与紧固', '精度校准', '电气系统检查', '液压系统保养', '冷却系统清洗', '安全防护检查'];

        foreach ($equipments as $i => $e) {
            $code = 'MP-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $dueDays = $i < 2 ? -1 - $i : (10 + $i * 5); // 前两台已到期

            MaintenancePlan::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $e->name . ' · ' . ($planNames[$i] ?? '周期保养'),
                    'equipment_id' => $e->id,
                    'cycle_days' => 30 + $i * 15,
                    'standard_minutes' => 60 + $i * 30,
                    'last_done_at' => now()->subDays(30 + $i * 15 - $dueDays)->toDateString(),
                    'next_due_at' => now()->addDays($dueDays)->toDateString(),
                    'is_active' => true,
                    'remark' => '演示数据：按周期自动到期',
                ]
            );
        }

        // 到期计划各生成一张待执行保养单
        foreach (MaintenancePlan::query()->whereDate('next_due_at', '<=', now()->toDateString())->get() as $idx => $plan) {
            MaintenanceOrder::query()->updateOrCreate(
                ['order_no' => 'MO-2026-' . str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'plan_id' => $plan->id,
                    'equipment_id' => $plan->equipment_id,
                    'type' => 'pm',
                    'status' => $idx === 0 ? 'pending' : 'in_progress',
                    'due_at' => $plan->next_due_at,
                    'remark' => '演示数据：到期保养',
                ]
            );
        }

        // 两张已完成的历史保养单（含工时，给 OEE / 报表用）
        $doneEquipments = Equipment::query()->orderBy('code')->skip(2)->limit(2)->get();
        foreach ($doneEquipments as $idx => $e) {
            MaintenanceOrder::query()->updateOrCreate(
                ['order_no' => 'MO-2026-900' . ($idx + 1)],
                [
                    'plan_id' => null,
                    'equipment_id' => $e->id,
                    'type' => 'cm',
                    'status' => 'done',
                    'due_at' => now()->subDays(9 - $idx)->toDateString(),
                    'done_at' => now()->subDays(8 - $idx)->setTime(15, 40),
                    'assignee_id' => null,
                    'cost_hours' => 2.5 + $idx,
                    'remark' => '演示数据：故障维修已完成',
                ]
            );
        }
    }
}
