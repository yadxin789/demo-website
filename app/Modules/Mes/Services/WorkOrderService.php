<?php

declare(strict_types=1);

namespace App\Modules\Mes\Services;

use App\Modules\Mes\Models\Bom;
use App\Modules\Mes\Models\Material;
use App\Modules\Mes\Models\Routing;
use App\Modules\Mes\Models\User;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Models\WorkOrderOperation;
use Illuminate\Support\Facades\DB;

/**
 * 工单服务（M2）：建单 / 下达 / 派工。
 *
 * 状态机（规划书 §2.3）：
 *   draft → released → in_progress → completed → closed
 * 只有 draft 能下达；下达时按工艺路线生成工序，之后才能派工与报工。
 */
class WorkOrderService
{
    /**
     * 建单：产品必填，BOM 与工艺路线按产品的 active 版本自动挂上。
     *
     * @param  array{product_material_id:int, plan_qty:float, plan_start?:?string, plan_end?:?string, priority?:int, customer?:?string, remark?:?string}  $data
     */
    public function create(array $data): WorkOrder
    {
        $productId = (int) $data['product_material_id'];

        $bom = Bom::query()
            ->where('product_material_id', $productId)
            ->where('status', 'active')
            ->first();

        $routing = Routing::query()
            ->where('product_material_id', $productId)
            ->where('status', 'active')
            ->first();

        return WorkOrder::query()->create([
            'order_no' => $this->nextOrderNo(),
            'product_material_id' => $productId,
            'bom_id' => $bom?->id,
            'routing_id' => $routing?->id,
            'plan_qty' => (float) $data['plan_qty'],
            'done_qty' => 0,
            'defect_qty' => 0,
            'status' => 'draft',
            'plan_start' => $data['plan_start'] ?? now()->toDateString(),
            'plan_end' => $data['plan_end'] ?? now()->addDays(7)->toDateString(),
            'priority' => (int) ($data['priority'] ?? 5),
            'customer' => $data['customer'] ?? null,
            'remark' => $data['remark'] ?? null,
        ]);
    }

    /**
     * 下达：draft → released，并按工艺路线生成工序。
     *
     * @return array{ok:bool, message:string, operations:int}
     */
    public function release(WorkOrder $order): array
    {
        if ($order->status !== 'draft') {
            return ['ok' => false, 'message' => '这张单已经下达过了，不用重复操作。', 'operations' => 0];
        }

        $created = 0;

        DB::connection('mes')->transaction(function () use ($order, &$created) {
            // 已有工序（例如 Excel 导入时生成过）就不重复建，只补齐缺的工序号
            $existing = $order->operations()->pluck('op_no')->all();
            $routing = $order->routing;

            if ($routing) {
                foreach ($routing->operations as $op) {
                    if (in_array($op->op_no, $existing, true)) {
                        continue;
                    }

                    $order->operations()->create([
                        'op_no' => $op->op_no,
                        'op_name' => $op->op_name,
                        'work_center_id' => $op->work_center_id,
                        'plan_qty' => $order->plan_qty,
                        'status' => 'pending',
                        'std_minutes' => $op->std_minutes,
                    ]);
                    $created++;
                }
            }

            $order->status = 'released';
            $order->save();
        });

        $message = $created > 0
            ? "已下达，按工艺生成了 {$created} 道工序，可以去派工了。"
            : '已下达（这张单的工序此前已经生成过）。';

        return ['ok' => true, 'message' => $message, 'operations' => $created];
    }

    /** 派工：把某道工序交给某个人 / 某个班组。 */
    public function dispatch(WorkOrderOperation $operation, ?int $assigneeId, ?string $team): void
    {
        $operation->assignee_id = $assigneeId;
        $operation->team = $team;
        if ($operation->status === 'pending') {
            $operation->status = 'dispatched';
        }
        $operation->save();
    }

    /**
     * 一键派工：把还没派的工序全部派给指定的人。
     *
     * @return int 实际派工数
     */
    public function dispatchAllPending(WorkOrder $order, ?int $assigneeId, ?string $team): int
    {
        $count = 0;
        foreach ($order->operations()->where('status', 'pending')->get() as $op) {
            $this->dispatch($op, $assigneeId, $team);
            $count++;
        }

        return $count;
    }

    /** 生成本年度下一个工单号：WO-2026-0021 */
    public function nextOrderNo(): string
    {
        $prefix = 'WO-' . now()->year . '-';

        $last = WorkOrder::query()
            ->where('order_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('order_no');

        $seq = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        // 极端情况下撞号（手工改过号），往后顺延
        while (WorkOrder::query()->where('order_no', $prefix . sprintf('%04d', $seq))->exists()) {
            $seq++;
        }

        return $prefix . sprintf('%04d', $seq);
    }

    /** 当前登录的班组长（派工默认对象） */
    public function defaultLeader(): ?User
    {
        return User::query()->where('name', 'like', '%班组%')->first()
            ?? User::query()->orderBy('id')->first();
    }

    /** 产品下拉（成品 / 半成品） */
    public function products()
    {
        return Material::query()
            ->whereIn('category', ['finished', 'semi'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'unit']);
    }
}
