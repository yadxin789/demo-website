<?php

namespace App\Modules\Shop\Services\Purchase;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\PurchaseOrderLine;
use App\Modules\Shop\Models\Tax;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 采购订单服务：行金额/折扣/税额计算，头汇总，状态机流转与审计留痕。
 * 采购方向为入库，不占用库存；收货入库在 P1-302 由 InboundService 处理。
 */
class PurchaseService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected AuditLogger $logger
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createOrder(array $data, array $lines): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $lines) {
            $order = PurchaseOrder::create([
                'code' => $this->sequences->generate('purchase_order'),
                'supplier_id' => (int) $data['supplier_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'order_date' => isset($data['order_date']) ? Carbon::parse($data['order_date']) : Carbon::today(),
                'expected_date' => isset($data['expected_date']) && $data['expected_date'] !== '' ? Carbon::parse($data['expected_date']) : null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'note' => $data['note'] ?? null,
                'creator_id' => $data['creator_id'] ?? null,
            ]);

            $this->syncLines($order, $lines);

            return $order->load('lines.product');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function updateOrder(PurchaseOrder $order, array $data, array $lines): PurchaseOrder
    {
        if (! $order->isEditable()) {
            throw new InventoryException("采购单 {$order->code} 非草稿状态，不能修改");
        }

        return DB::transaction(function () use ($order, $data, $lines) {
            $beforeHead = $order->only(['supplier_id', 'warehouse_id', 'note', 'total_qty', 'total_amount', 'grand_total']);
            $beforeLines = $order->lines->map(fn ($l) => [
                'product_id' => $l->product_id,
                'qty' => (float) $l->qty,
                'unit_price' => (float) $l->unit_price,
                'discount_rate' => (float) $l->discount_rate,
                'subtotal' => (float) $l->subtotal,
            ])->values();

            $order->update([
                'supplier_id' => (int) $data['supplier_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'order_date' => isset($data['order_date']) ? Carbon::parse($data['order_date']) : $order->order_date,
                'expected_date' => isset($data['expected_date']) && $data['expected_date'] !== '' ? Carbon::parse($data['expected_date']) : null,
                'note' => $data['note'] ?? null,
            ]);

            $order->lines()->delete();
            $this->syncLines($order, $lines);

            $afterHead = $order->fresh()->only(['supplier_id', 'warehouse_id', 'note', 'total_qty', 'total_amount', 'grand_total']);
            $afterLines = $order->fresh()->lines->map(fn ($l) => [
                'product_id' => $l->product_id,
                'qty' => (float) $l->qty,
                'unit_price' => (float) $l->unit_price,
                'discount_rate' => (float) $l->discount_rate,
                'subtotal' => (float) $l->subtotal,
            ])->values();

            $this->logger->record([
                'module' => 'purchase',
                'action' => AuditLog::ACTION_UPDATE,
                'model' => PurchaseOrder::class,
                'record_id' => $order->id,
                'document_no' => $order->code,
                'field' => 'lines',
                'old_value' => $beforeLines->all(),
                'new_value' => $afterLines->all(),
                'context' => ['before_head' => $beforeHead, 'after_head' => $afterHead],
                'message' => "采购单 {$order->code} 明细数量/单价/折扣变更",
            ]);

            return $order->load('lines.product');
        });
    }

    /**
     * 状态流转；确认/取消等关键动作写审计。
     */
    public function transition(PurchaseOrder $order, string $status, ?int $operatorId = null): PurchaseOrder
    {
        if (! $order->canTransitionTo($status)) {
            throw new InventoryException("采购单 {$order->code} 不能从 {$order->status} 流转到 {$status}");
        }

        $before = $order->status;
        $order->status = $status;

        if ($status === PurchaseOrder::STATUS_CONFIRMED) {
            $order->approver_id = $operatorId;
            $order->approved_at = Carbon::now();
        }

        $order->save();

        $this->logger->record([
            'module' => 'purchase',
            'action' => AuditLog::ACTION_UPDATE,
            'model' => PurchaseOrder::class,
            'record_id' => $order->id,
            'document_no' => $order->code,
            'field' => 'status',
            'old_value' => ['value' => $before],
            'new_value' => ['value' => $status],
            'message' => "采购单 {$order->code} 状态：{$before} → {$status}",
        ]);

        return $order;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(PurchaseOrder $order, array $lines): void
    {
        if ($lines === []) {
            throw new InventoryException('采购单至少需要一行商品');
        }

        $totalQty = 0;
        $totalAmount = 0;
        $discountAmount = 0;
        $taxAmount = 0;

        foreach ($lines as $line) {
            $product = Product::findOrFail($line['product_id']);
            $qty = (float) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                throw new InventoryException("商品 {$product->code} 采购数量必须大于 0");
            }

            $unitPrice = (float) ($line['unit_price'] ?? $product->standard_cost);
            $discountRate = (float) ($line['discount_rate'] ?? 0);
            if ($discountRate < 0 || $discountRate > 100) {
                throw new InventoryException("商品 {$product->code} 折扣率必须在 0~100 之间");
            }

            $taxId = (int) ($line['tax_id'] ?? $product->tax_id ?? 0) ?: null;
            $tax = $taxId ? Tax::find($taxId) : null;
            $rate = $tax ? (float) $tax->rate : 0;

            $gross = $qty * $unitPrice;
            $subtotal = $gross * (1 - $discountRate / 100);
            $lineTax = $this->lineTax($subtotal, $rate, $tax?->type);

            PurchaseOrderLine::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'tax_id' => $taxId,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'discount_rate' => $discountRate,
                'subtotal' => $subtotal,
                'tax_amount' => $lineTax,
                'line_note' => $line['line_note'] ?? null,
            ]);

            $totalQty += $qty;
            $totalAmount += $subtotal;
            $discountAmount += $gross - $subtotal;
            $taxAmount += $lineTax;
        }

        $order->update([
            'total_qty' => $totalQty,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'grand_total' => $totalAmount + $taxAmount,
        ]);
    }

    private function lineTax(float $subtotal, float $rate, ?string $type): float
    {
        // 本期按不含税（exclusive）计算；含税反推为 P2 预留。
        return round($subtotal * $rate / 100, 4);
    }
}
