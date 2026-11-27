<?php

declare(strict_types=1);

namespace App\Modules\Mes\Services;

use App\Modules\Mes\Models\FinishedReceipt;
use App\Modules\Mes\Models\Lot;
use App\Modules\Mes\Models\Material;
use App\Modules\Mes\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 完工入库与关单（MES，M7 增补）。
 *
 * 闭环：报工累计（done_qty）→ 完工入库（可分批，带批次号）→ 累计入库量达到计划量转 completed → 人工关单 closed。
 * 批次：每张入库单生成一个 lot，lot.parent_lot_no 取该工单领料单里"用量最大"的那批料，用于上下游追溯。
 */
class CompletionService
{
    /** 该工单还可以入库的数量（计划量 - 已入库） */
    public function receivable(WorkOrder $order): float
    {
        $received = (float) FinishedReceipt::query()->where('work_order_id', $order->id)->sum('qty');

        return max(0, round((float) $order->plan_qty - $received, 3));
    }

    /**
     * 入库。
     *
     * @param  array{qty:float|string, warehouse?:?string, lot_no?:?string, remark?:?string}  $data
     * @return array{ok:bool, message:string, receipt:?FinishedReceipt}
     */
    public function receive(WorkOrder $order, array $data, ?int $userId): array
    {
        if (! in_array($order->status, ['released', 'in_progress'], true)) {
            return ['ok' => false, 'message' => '这张单还没下达或已经完工，不能入库。', 'receipt' => null];
        }

        $qty = round((float) $data['qty'], 3);
        if ($qty <= 0) {
            return ['ok' => false, 'message' => '入库数量要大于 0。', 'receipt' => null];
        }

        $remain = $this->receivable($order);
        if ($qty > $remain) {
            return ['ok' => false, 'message' => "最多还能入库 {$remain}，别超过计划量。", 'receipt' => null];
        }

        $lotNo = trim((string) ($data['lot_no'] ?? '')) ?: $this->nextLotNo();

        $receipt = DB::connection('mes')->transaction(function () use ($order, $qty, $lotNo, $data, $userId) {
            $receipt = FinishedReceipt::query()->create([
                'receipt_no' => $this->nextReceiptNo(),
                'work_order_id' => $order->id,
                'material_id' => $order->product_material_id,
                'lot_no' => $lotNo,
                'qty' => $qty,
                'warehouse' => $data['warehouse'] ?? '成品库',
                'received_by' => $userId,
                'received_at' => now(),
                'remark' => $data['remark'] ?? null,
            ]);

            // 批次台账：成品批次，父批次取领料最多的那批料（没有就留空）
            Lot::query()->updateOrCreate(
                ['lot_no' => $lotNo],
                [
                    'material_id' => $order->product_material_id,
                    'source' => 'production',
                    'work_order_id' => $order->id,
                    'qty' => $qty,
                    'parent_lot_no' => $this->mainComponentLot($order),
                    'produced_at' => now()->toDateString(),
                ]
            );

            // 成品入库，库存加回来
            Material::query()->whereKey($order->product_material_id)->increment('stock_qty', $qty);

            // 入库量够了就自动转完工
            if ($this->receivable($order->fresh()) <= 0) {
                $order->status = 'completed';
                $order->save();
            }

            return $receipt;
        });

        $done = $order->fresh();
        $message = $done->status === 'completed'
            ? "已入库 {$qty}（批次 {$lotNo}），计划量已入满，工单转为完工，可以关单了。"
            : "已入库 {$qty}（批次 {$lotNo}），还剩 {$this->receivable($done)} 未入库。";

        return ['ok' => true, 'message' => $message, 'receipt' => $receipt];
    }

    /**
     * 关单。
     *
     * @return array{ok:bool, message:string}
     */
    public function close(WorkOrder $order): array
    {
        if ($order->status === 'closed') {
            return ['ok' => false, 'message' => '这张单已经关过了。'];
        }

        if ($order->status !== 'completed') {
            return ['ok' => false, 'message' => '还没完工（入库没满），先入库再关单。'];
        }

        $openOps = $order->operations()->whereNotIn('status', ['done'])->count();
        if ($openOps > 0) {
            return ['ok' => false, 'message' => "还有 {$openOps} 道工序没完成，先把工序走完。"];
        }

        $order->status = 'closed';
        $order->save();

        return ['ok' => true, 'message' => '工单已关单，这张单的账就算结了。'];
    }

    /** 领料单里用量最大的那批料（追溯父批次；台账里没有对应批次就留空） */
    private function mainComponentLot(WorkOrder $order): ?string
    {
        $line = $order->issues()
            ->with('lines')
            ->get()
            ->flatMap(fn ($issue) => $issue->lines)
            ->sortByDesc(fn ($line) => (float) $line->issued_qty)
            ->first();

        if (! $line) {
            return null;
        }

        return Lot::query()
            ->where('material_id', $line->material_id)
            ->where('source', '!=', 'production')
            ->orderByDesc('id')
            ->value('lot_no');
    }

    public function nextReceiptNo(): string
    {
        $prefix = 'FR-' . now()->format('Y') . '-';
        $last = FinishedReceipt::query()->where('receipt_no', 'like', $prefix . '%')->orderByDesc('receipt_no')->value('receipt_no');
        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function nextLotNo(): string
    {
        $prefix = 'LOT-' . now()->format('Ymd') . '-';
        $last = Lot::query()->where('lot_no', 'like', $prefix . '%')->orderByDesc('lot_no')->value('lot_no');
        $seq = $last ? ((int) substr((string) $last, -3)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
