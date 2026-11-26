<?php

namespace App\Modules\Shop\Services\Purchase;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\PurchaseOrderLine;
use App\Modules\Shop\Models\Receipt;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Inventory\InboundService;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 收货入库单：关联采购单，按批次/序列号调用 InboundService 入库；
 * 超收拦截（容差本期为 0，预留配置）；回写采购单收货进度（部分/全部）。
 */
class ReceiptService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected InboundService $inbound,
        protected EntryService $entries,
        protected AuditLogger $logger
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function receive(array $data, array $lines): Receipt
    {
        if ($lines === []) {
            throw new InventoryException('收货单至少需要一行商品');
        }

        return DB::transaction(function () use ($data, $lines) {
            $po = PurchaseOrder::findOrFail($data['purchase_order_id']);
            $po->loadMissing('lines.product');
            $lines = $this->validateSerialLines($lines, $po);
            $entryLines = [];

            if (! in_array($po->status, [PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIAL], true)) {
                throw new InventoryException("采购单 {$po->code} 当前状态不能收货");
            }

            $receipt = Receipt::create([
                'code' => $this->sequences->generate('stock_in'),
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'warehouse_id' => (int) $data['warehouse_id'],
                'receipt_date' => isset($data['receipt_date']) ? Carbon::parse($data['receipt_date']) : Carbon::today(),
                'status' => Receipt::STATUS_DONE,
                'operator_id' => $data['operator_id'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                $poLine = PurchaseOrderLine::findOrFail($line['purchase_order_line_id']);
                if ((int) $poLine->order_id !== $po->id) {
                    throw new InventoryException('收货行不属于该采购单');
                }

                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new InventoryException("商品 {$poLine->product?->code} 收货数量必须大于 0");
                }

                $received = $this->receivedQty($poLine->id);
                if ($received + $qty > (float) $poLine->qty) {
                    throw new InventoryException(
                        "商品 {$poLine->product?->code} 超收：订单 {$poLine->qty}，已收 {$received}，本次 {$qty}"
                    );
                }

                $unitCost = (float) ($line['unit_cost'] ?? $poLine->unit_price);
                $stdCost = (float) ($poLine->product?->standard_cost ?? 0);
                $amount = round($qty * ($stdCost > 0 ? $stdCost : $unitCost), 4);

                $this->inbound->inbound([
                    'product_id' => $poLine->product_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'location_id' => $line['location_id'] ?? null,
                    'qty' => $qty,
                    'lot_no' => $line['lot_no'] ?? null,
                    'serials' => $line['serials'] ?? [],
                    'unit_cost' => $unitCost,
                    'source_type' => 'receipt',
                    'source_id' => $receipt->id,
                    'source_code' => $receipt->code,
                    'operator_id' => $receipt->operator_id,
                    'note' => $line['line_note'] ?? null,
                ]);

                $receipt->lines()->create([
                    'purchase_order_line_id' => $poLine->id,
                    'product_id' => $poLine->product_id,
                    'location_id' => $line['location_id'] ?? null,
                    'lot_no' => $line['lot_no'] ?? null,
                    'serial_no' => $line['serials'][0] ?? ($line['serial_no'] ?? null),
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'line_note' => $line['line_note'] ?? null,
                ]);

                if ($amount > 0) {
                    $memo = "{$poLine->product?->code} {$qty}";
                    $entryLines[] = ['account_code' => '1001', 'direction' => 'debit', 'amount' => $amount, 'memo' => $memo];
                    $entryLines[] = ['account_code' => '2002', 'direction' => 'credit', 'amount' => $amount, 'memo' => $memo];
                }
            }

            $this->postEstimateEntry($receipt, $entryLines);

            $this->syncPurchaseOrderStatus($po);

            $this->logger->record([
                'module' => 'purchase',
                'action' => AuditLog::ACTION_CREATE,
                'model' => Receipt::class,
                'record_id' => $receipt->id,
                'document_no' => $receipt->code,
                'message' => "收货单 {$receipt->code} 入库，关联采购单 {$po->code}",
                'context' => [
                    'purchase_order_id' => $po->id,
                    'line_count' => $receipt->lines->count(),
                ],
            ]);

            return $receipt->load('lines.product');
        });
    }

    /**
     * 序列号商品收货校验：行归属、商品一致、逐号数量匹配、全单序列号不重复。
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function validateSerialLines(array $lines, PurchaseOrder $po): array
    {
        $seen = [];

        foreach ($lines as $index => $line) {
            $poLine = PurchaseOrderLine::findOrFail($line['purchase_order_line_id']);

            if ((int) $poLine->order_id !== $po->id) {
                throw new InventoryException('收货行不属于该采购单');
            }

            if ((int) $line['product_id'] !== (int) $poLine->product_id) {
                throw new InventoryException('收货行商品与采购单行不一致');
            }

            $raw = $line['serials'] ?? [];
            if (is_string($raw)) {
                $raw = preg_split('/[\s,，、]+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }

            $serials = is_array($raw)
                ? collect($raw)->map(fn ($serial) => trim((string) $serial))->filter()->values()->all()
                : [];

            $lines[$index]['serials'] = $serials;

            if (($poLine->product?->traceability ?? 'none') !== 'serial') {
                continue;
            }

            $qty = (float) ($line['qty'] ?? 0);
            if (abs($qty - count($serials)) > 0.0000001 || abs($qty - round($qty)) > 0.0000001) {
                throw new InventoryException(
                    "商品 {$poLine->product->code} 为序列号追踪，收货数量 {$qty} 必须等于录入序列号数量 " . count($serials)
                );
            }

            foreach ($serials as $serial) {
                if ($serial === '' || mb_strlen($serial) > 50) {
                    throw new InventoryException('序列号不能为空且不能超过 50 个字符');
                }
                if (isset($seen[$serial])) {
                    throw new InventoryException("序列号 {$serial} 在本次收货中重复录入");
                }
                $seen[$serial] = true;
            }
        }

        return array_values($lines);
    }

    /** 采购入库票未到：借 库存商品 / 贷 应付暂估，按标准成本金额生成并过账。 */
    private function postEstimateEntry(Receipt $receipt, array $entryLines): void
    {
        if ($entryLines === []) {
            return;
        }

        $journal = Journal::where('code', 'PURCHASE')->firstOrFail();
        $entry = $this->entries->createEntry([
            'journal_id' => $journal->id,
            'entry_date' => $receipt->receipt_date?->toDateString(),
            'source_type' => 'receipt',
            'source_id' => $receipt->id,
            'source_code' => $receipt->code,
            'description' => "采购入库暂估 {$receipt->code}",
        ], $entryLines);

        $this->entries->post($entry);
    }

    /** 采购行累计已收数量（仅统计已入库收货单）。 */
    private function receivedQty(int $poLineId): float
    {
        return (float) \App\Modules\Shop\Models\ReceiptLine::where('purchase_order_line_id', $poLineId)
            ->whereHas('receipt', fn ($query) => $query->where('status', Receipt::STATUS_DONE))
            ->sum('qty');
    }

    /** 根据各行收齐情况回写采购单状态。 */
    private function syncPurchaseOrderStatus(PurchaseOrder $po): void
    {
        $allFilled = true;

        foreach ($po->lines as $line) {
            if ($this->receivedQty($line->id) < (float) $line->qty) {
                $allFilled = false;
                break;
            }
        }

        $po->status = $allFilled ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIAL;
        $po->save();
    }
}
