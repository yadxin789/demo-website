<?php

namespace App\Modules\Shop\Services\Sales;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\Tax;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Inventory\ReservationService;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 销售订单服务：取价（默认零售价，可手工覆盖）、折扣、税额与头汇总，
 * 状态机流转与前后值审计留痕。出库扣减与成本结转在 P1-402 处理。
 */
class SalesService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected AuditLogger $logger,
        protected ReservationService $reservations
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createOrder(array $data, array $lines): SalesOrder
    {
        return DB::transaction(function () use ($data, $lines) {
            $order = SalesOrder::create([
                'code' => $this->sequences->generate('sales_order'),
                'customer_id' => (int) $data['customer_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'order_date' => isset($data['order_date']) ? Carbon::parse($data['order_date']) : Carbon::today(),
                'expected_date' => isset($data['expected_date']) && $data['expected_date'] !== '' ? Carbon::parse($data['expected_date']) : null,
                'status' => SalesOrder::STATUS_DRAFT,
                'note' => $data['note'] ?? null,
                'creator_id' => $data['creator_id'] ?? null,
            ]);

            $this->syncLines($order, $lines);

            return $order->load('lines.product');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function updateOrder(SalesOrder $order, array $data, array $lines): SalesOrder
    {
        if (! $order->isEditable()) {
            throw new InventoryException("销售单 {$order->code} 非草稿状态，不能修改");
        }

        return DB::transaction(function () use ($order, $data, $lines) {
            $beforeLines = $order->lines->map(fn ($l) => [
                'product_id' => $l->product_id,
                'qty' => (float) $l->qty,
                'unit_price' => (float) $l->unit_price,
                'discount_rate' => (float) $l->discount_rate,
                'subtotal' => (float) $l->subtotal,
            ])->values();

            $order->update([
                'customer_id' => (int) $data['customer_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'order_date' => isset($data['order_date']) ? Carbon::parse($data['order_date']) : $order->order_date,
                'expected_date' => isset($data['expected_date']) && $data['expected_date'] !== '' ? Carbon::parse($data['expected_date']) : null,
                'note' => $data['note'] ?? null,
            ]);

            $order->lines()->delete();
            $this->syncLines($order, $lines);

            $afterLines = $order->fresh()->lines->map(fn ($l) => [
                'product_id' => $l->product_id,
                'qty' => (float) $l->qty,
                'unit_price' => (float) $l->unit_price,
                'discount_rate' => (float) $l->discount_rate,
                'subtotal' => (float) $l->subtotal,
            ])->values();

            $this->logger->record([
                'module' => 'sales',
                'action' => AuditLog::ACTION_UPDATE,
                'model' => SalesOrder::class,
                'record_id' => $order->id,
                'document_no' => $order->code,
                'field' => 'lines',
                'old_value' => $beforeLines->all(),
                'new_value' => $afterLines->all(),
                'message' => "销售单 {$order->code} 明细数量/单价/折扣变更",
            ]);

            return $order->load('lines.product');
        });
    }

    /**
     * 状态流转；确认/取消/发货等关键动作写审计。
     */
    public function transition(SalesOrder $order, string $status, ?int $operatorId = null): SalesOrder
    {
        if (! $order->canTransitionTo($status)) {
            throw new InventoryException("销售单 {$order->code} 不能从 {$order->status} 流转到 {$status}");
        }

        $before = $order->status;

        if ($status === SalesOrder::STATUS_CONFIRMED) {
            // 占用预留量；可用库存不足时抛出异常，状态不落库。
            $this->reservations->reserve($order);
        } elseif ($status === SalesOrder::STATUS_CANCELLED && $before === SalesOrder::STATUS_CONFIRMED) {
            // 已确认订单取消，释放占用的预留量。
            $this->reservations->release($order);
        }

        $order->status = $status;

        if ($status === SalesOrder::STATUS_CONFIRMED) {
            $order->approver_id = $operatorId;
            $order->approved_at = Carbon::now();
        }

        $order->save();

        $this->logger->record([
            'module' => 'sales',
            'action' => AuditLog::ACTION_UPDATE,
            'model' => SalesOrder::class,
            'record_id' => $order->id,
            'document_no' => $order->code,
            'field' => 'status',
            'old_value' => ['value' => $before],
            'new_value' => ['value' => $status],
            'message' => "销售单 {$order->code} 状态：{$before} → {$status}",
        ]);

        return $order;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(SalesOrder $order, array $lines): void
    {
        if ($lines === []) {
            throw new InventoryException('销售单至少需要一行商品');
        }

        $totalQty = 0;
        $totalAmount = 0;
        $discountAmount = 0;
        $taxAmount = 0;

        foreach ($lines as $line) {
            $product = Product::findOrFail($line['product_id']);
            $qty = (float) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                throw new InventoryException("商品 {$product->code} 销售数量必须大于 0");
            }

            $unitPrice = (float) ($line['unit_price'] ?? $product->list_price);
            $discountRate = (float) ($line['discount_rate'] ?? 0);
            if ($discountRate < 0 || $discountRate > 100) {
                throw new InventoryException("商品 {$product->code} 折扣率必须在 0~100 之间");
            }

            $taxId = (int) ($line['tax_id'] ?? $product->tax_id ?? 0) ?: null;
            $tax = $taxId ? Tax::find($taxId) : null;
            $rate = $tax ? (float) $tax->rate : 0;

            $gross = $qty * $unitPrice;
            $subtotal = $gross * (1 - $discountRate / 100);
            $lineTax = round($subtotal * $rate / 100, 4);

            $order->lines()->create([
                'product_id' => $product->id,
                'lot_no' => $line['lot_no'] ?? null,
                'serial_no' => $line['serial_no'] ?? null,
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
}
