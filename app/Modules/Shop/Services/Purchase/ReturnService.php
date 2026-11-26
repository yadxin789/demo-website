<?php

namespace App\Modules\Shop\Services\Purchase;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseReturn;
use App\Modules\Shop\Models\PurchaseReturnLine;
use App\Modules\Shop\Models\Receipt;
use App\Modules\Shop\Models\ReceiptLine;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Inventory\OutboundService;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 采购退货服务：关联已收货单退货出库，冲减暂估应付并生成反向平衡分录。
 * 草稿阶段不扣库存；过账时逐行出库、生成退货分录并锁定单据。退货数量不得超过已入库未退数量。
 */
class ReturnService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected OutboundService $outbound,
        protected EntryService $entries,
        protected AuditLogger $logger
    ) {
    }

    /**
     * 创建采购退货草稿单。
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createReturn(array $data, array $lines): PurchaseReturn
    {
        if ($lines === []) {
            throw new InventoryException('退货单至少需要一行商品');
        }

        return DB::transaction(function () use ($data, $lines) {
            $receipt = Receipt::findOrFail($data['receipt_id']);

            if ($receipt->status !== Receipt::STATUS_DONE) {
                throw new InventoryException("收货单 {$receipt->code} 当前状态不能退货");
            }

            $return = PurchaseReturn::create([
                'code' => $this->sequences->generate('return_purchase'),
                'receipt_id' => $receipt->id,
                'purchase_order_id' => $receipt->purchase_order_id,
                'supplier_id' => $receipt->supplier_id,
                'warehouse_id' => $receipt->warehouse_id,
                'return_date' => isset($data['return_date']) ? Carbon::parse($data['return_date']) : Carbon::today(),
                'status' => PurchaseReturn::STATUS_DRAFT,
                'reason' => $data['reason'] ?? null,
                'operator_id' => $data['operator_id'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                $receiptLine = ReceiptLine::findOrFail($line['receipt_line_id']);
                if ((int) $receiptLine->receipt_id !== $receipt->id) {
                    throw new InventoryException('退货行不属于该收货单');
                }

                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new InventoryException("商品 {$receiptLine->product?->code} 退货数量必须大于 0");
                }

                $this->assertReturnable($receiptLine, $qty);

                $unitCost = (float) ($line['unit_cost'] ?? $receiptLine->unit_cost);

                $return->lines()->create([
                    'receipt_line_id' => $receiptLine->id,
                    'purchase_order_line_id' => $receiptLine->purchase_order_line_id,
                    'product_id' => $receiptLine->product_id,
                    'location_id' => $line['location_id'] ?? $receiptLine->location_id,
                    'lot_no' => $line['lot_no'] ?? $receiptLine->lot_no,
                    'serial_no' => $line['serial_no'] ?? $receiptLine->serial_no,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'reason' => $line['reason'] ?? null,
                ]);
            }

            $this->logger->record([
                'module' => 'purchase',
                'action' => AuditLog::ACTION_CREATE,
                'model' => PurchaseReturn::class,
                'record_id' => $return->id,
                'document_no' => $return->code,
                'message' => "采购退货单 {$return->code} 草稿创建，关联收货单 {$receipt->code}",
                'context' => [
                    'receipt_id' => $receipt->id,
                    'supplier_id' => $receipt->supplier_id,
                    'line_count' => $return->lines->count(),
                ],
            ]);

            return $return->load('lines.product');
        });
    }

    /**
     * 退货过账：逐行出库扣减库存，生成反向冲回分录并锁定单据。
     */
    public function postReturn(PurchaseReturn $return): PurchaseReturn
    {
        if ($return->status === PurchaseReturn::STATUS_DONE) {
            throw new InventoryException("退货单 {$return->code} 已过账，不能重复过账");
        }

        if ($return->status === PurchaseReturn::STATUS_CANCELLED) {
            throw new InventoryException("退货单 {$return->code} 已取消，不能过账");
        }

        return DB::transaction(function () use ($return) {
            $entryLines = [];
            $totalAmount = 0;

            foreach ($return->lines as $line) {
                $receiptLine = $line->receiptLine;
                if ($receiptLine) {
                    $this->assertReturnable($receiptLine, (float) $line->qty, $return->id);
                }

                $product = Product::findOrFail($line->product_id);
                $qty = (float) $line->qty;
                $stdCost = (float) ($product->standard_cost > 0 ? $product->standard_cost : $line->unit_cost);
                $amount = round($qty * $stdCost, 4);
                $totalAmount += $amount;

                $this->outbound->outbound([
                    'product_id' => $product->id,
                    'warehouse_id' => $return->warehouse_id,
                    'location_id' => $line->location_id,
                    'qty' => $qty,
                    'lot_no' => $line->lot_no,
                    'serials' => $product->traceability === 'serial' && $line->serial_no ? [$line->serial_no] : [],
                    'unit_cost' => $stdCost,
                    'source_type' => 'purchase_return',
                    'source_id' => $return->id,
                    'source_code' => $return->code,
                    'operator_id' => $return->operator_id,
                    'note' => $line->reason ?? '采购退货',
                    'movement_date' => $return->return_date?->toDateString(),
                ]);

                if ($amount > 0) {
                    $memo = "{$product->code} 退货 {$qty}";
                    $entryLines[] = ['account_code' => '2002', 'direction' => 'debit', 'amount' => $amount, 'memo' => $memo];
                    $entryLines[] = ['account_code' => '1001', 'direction' => 'credit', 'amount' => $amount, 'memo' => $memo];
                }
            }

            $this->postReversalEntry($return, $entryLines);

            $return->status = PurchaseReturn::STATUS_DONE;
            $return->save();

            $this->logger->record([
                'module' => 'purchase',
                'action' => AuditLog::ACTION_APPROVE,
                'model' => PurchaseReturn::class,
                'record_id' => $return->id,
                'document_no' => $return->code,
                'message' => "采购退货单 {$return->code} 过账，退货成本金额 " . number_format($totalAmount, 4),
                'context' => [
                    'receipt_id' => $return->receipt_id,
                    'supplier_id' => $return->supplier_id,
                    'line_count' => $return->lines->count(),
                    'total_amount' => $totalAmount,
                ],
            ]);

            return $return->load('lines.product');
        });
    }

    /** 收货行累计已退数量（仅统计已过账退货单）。 */
    public function returnedQty(int $receiptLineId, ?int $excludeReturnId = null): float
    {
        return (float) PurchaseReturnLine::where('receipt_line_id', $receiptLineId)
            ->when($excludeReturnId !== null,
                fn ($query) => $query->whereHas('purchaseReturn', fn ($q) => $q->where('id', '!=', $excludeReturnId)))
            ->whereHas('purchaseReturn', fn ($query) => $query->where('status', PurchaseReturn::STATUS_DONE))
            ->sum('qty');
    }

    /** 校验本次退货数量不超过已入库未退数量。 */
    private function assertReturnable(ReceiptLine $receiptLine, float $qty, ?int $excludeReturnId = null): void
    {
        $received = (float) $receiptLine->qty;
        $returned = $this->returnedQty($receiptLine->id, $excludeReturnId);

        if ($returned + $qty > $received + 0.0000001) {
            throw new InventoryException(
                "商品 {$receiptLine->product?->code} 超退：已入库 {$received}，已退 {$returned}，本次 {$qty}"
            );
        }
    }

    /** 采购退货反向分录：借 应付暂估 / 贷 库存商品，按标准成本金额生成并过账。 */
    private function postReversalEntry(PurchaseReturn $return, array $entryLines): void
    {
        if ($entryLines === []) {
            return;
        }

        $journal = Journal::where('code', 'PURCHASE')->firstOrFail();
        $entry = $this->entries->createEntry([
            'journal_id' => $journal->id,
            'entry_date' => $return->return_date?->toDateString(),
            'source_type' => 'purchase_return',
            'source_id' => $return->id,
            'source_code' => $return->code,
            'description' => "采购退货冲回 {$return->code}",
        ], $entryLines);

        $this->entries->post($entry);
    }
}
