<?php

namespace App\Modules\Shop\Services\Sales;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\PosOrderLine;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\SalesOrderLine;
use App\Modules\Shop\Models\SalesReturn;
use App\Modules\Shop\Models\SalesReturnLine;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Inventory\InboundService;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 销售/POS 退货退款服务：原单部分或整单退货，商品回库位，
 * 红字冲回收入与销项税、红字冲回 COGS 与库存；退款可冲应收或退现金/银行。
 * 草稿阶段不回库、不生成分录；过账时在同一事务内完成库存回补、分录与状态回写。
 */
class ReturnService
{
    /** 退款方式 → 贷方会计科目编码。 */
    private const REFUND_CREDIT_MAP = [
        SalesReturn::REFUND_CREDIT => '1003',
        SalesReturn::REFUND_CASH => '1004',
        SalesReturn::REFUND_BANK => '1005',
    ];

    public function __construct(
        protected SequenceGenerator $sequences,
        protected InboundService $inbound,
        protected EntryService $entries,
        protected AuditLogger $logger
    ) {
    }

    /**
     * 创建销售退货草稿单。
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createReturn(array $data, array $lines): SalesReturn
    {
        if ($lines === []) {
            throw new InventoryException('退货单至少需要一行商品');
        }

        return DB::transaction(function () use ($data, $lines) {
            [$source, $order, $customerId, $warehouseId, $locationId] = $this->resolveSource($data);

            $return = SalesReturn::create([
                'code' => $this->sequences->generate('return_sale'),
                'source_type' => $source,
                'sales_order_id' => $source === SalesReturn::SOURCE_SALES_ORDER ? $order->id : null,
                'pos_order_id' => $source === SalesReturn::SOURCE_POS_ORDER ? $order->id : null,
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'return_date' => isset($data['return_date']) ? Carbon::parse($data['return_date']) : Carbon::today(),
                'status' => SalesReturn::STATUS_DRAFT,
                'reason' => $data['reason'] ?? null,
                'refund_method' => $data['refund_method'] ?? SalesReturn::REFUND_CREDIT,
                'refund_amount' => 0,
                'operator_id' => $data['operator_id'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                [$originalLine, $netUnit, $unitTax] = $this->resolveLine($source, $order, $line);

                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new InventoryException("商品 {$originalLine->product?->code} 退货数量必须大于 0");
                }

                $this->assertReturnable($source, $originalLine, $qty, $return->id);

                $lineNet = round($netUnit * $qty, 4);
                $lineTax = round($unitTax * $qty, 4);

                $return->lines()->create([
                    'sales_order_line_id' => $source === SalesReturn::SOURCE_SALES_ORDER ? $originalLine->id : null,
                    'pos_order_line_id' => $source === SalesReturn::SOURCE_POS_ORDER ? $originalLine->id : null,
                    'product_id' => $originalLine->product_id,
                    'location_id' => $line['location_id'] ?? $locationId,
                    'lot_no' => $line['lot_no'] ?? ($originalLine->lot_no ?? null),
                    'serial_no' => $line['serial_no'] ?? ($originalLine->serial_no ?? null),
                    'qty' => $qty,
                    'unit_price' => round($netUnit, 4),
                    'tax_amount' => $lineTax,
                    'line_total' => round($lineNet + $lineTax, 4),
                    'reason' => $line['reason'] ?? null,
                ]);
            }

            $this->logger->record([
                'module' => 'sales',
                'action' => AuditLog::ACTION_CREATE,
                'model' => SalesReturn::class,
                'record_id' => $return->id,
                'document_no' => $return->code,
                'message' => "销售退货单 {$return->code} 草稿创建，原单 {$order->code}",
                'context' => [
                    'source_type' => $source,
                    'source_id' => $order->id,
                    'customer_id' => $customerId,
                    'line_count' => $return->lines->count(),
                ],
            ]);

            return $return->load('lines.product');
        });
    }

    /**
     * 退货过账：商品回库、成本回库分录、收入与销项税红字冲回、退款核销；回写原单状态。
     */
    public function postReturn(SalesReturn $return): SalesReturn
    {
        if ($return->status === SalesReturn::STATUS_DONE) {
            throw new InventoryException("退货单 {$return->code} 已过账，不能重复过账");
        }

        if ($return->status === SalesReturn::STATUS_CANCELLED) {
            throw new InventoryException("退货单 {$return->code} 已取消，不能过账");
        }

        return DB::transaction(function () use ($return) {
            $cogsLines = [];
            $refundNet = 0.0;
            $refundTax = 0.0;
            $refundTotal = 0.0;

            foreach ($return->lines as $line) {
                $product = Product::findOrFail($line->product_id);
                $qty = (float) $line->qty;
                $stdCost = (float) ($product->standard_cost > 0 ? $product->standard_cost : 0);
                $cogs = round($qty * $stdCost, 4);

                $serials = $product->traceability === 'serial' && $line->serial_no ? [$line->serial_no] : [];

                $this->inbound->inbound([
                    'product_id' => $product->id,
                    'warehouse_id' => $return->warehouse_id,
                    'location_id' => $line->location_id,
                    'qty' => $qty,
                    'lot_no' => $line->lot_no,
                    'serials' => $serials,
                    'unit_cost' => $stdCost,
                    'source_type' => 'sales_return',
                    'source_id' => $return->id,
                    'source_code' => $return->code,
                    'operator_id' => $return->operator_id,
                    'note' => "销售退货回库 {$product->code}",
                    'movement_date' => $return->return_date?->toDateString(),
                ]);

                if ($cogs > 0) {
                    $memo = "{$product->code} {$qty}";
                    $cogsLines[] = ['account_code' => '1001', 'direction' => 'debit', 'amount' => $cogs, 'memo' => $memo];
                    $cogsLines[] = ['account_code' => '5001', 'direction' => 'credit', 'amount' => $cogs, 'memo' => $memo];
                }

                $refundNet += round((float) $line->unit_price * $qty, 4);
                $refundTax += (float) $line->tax_amount;
                $refundTotal += (float) $line->line_total;
            }

            $this->postCogsReversal($return, $cogsLines);

            // 先冲减原订单挂账应收，再按实际冲减额拆分退款贷方（差额转预收）。
            [$invoice, $invoiceApplied] = $this->applyRefundToInvoice($return, round($refundTotal, 4));
            $this->postRefundEntry($return, round($refundNet, 4), round($refundTax, 4), $invoiceApplied);

            $return->refund_amount = round($refundTotal, 4);
            $return->status = SalesReturn::STATUS_DONE;
            $return->save();

            $this->syncSourceOrder($return);

            $this->logger->record([
                'module' => 'sales',
                'action' => AuditLog::ACTION_REFUND,
                'model' => SalesReturn::class,
                'record_id' => $return->id,
                'document_no' => $return->code,
                'message' => "销售退货单 {$return->code} 过账，退款 {$refundTotal}（{$return->refund_method}）",
                'context' => [
                    'source_type' => $return->source_type,
                    'sales_order_id' => $return->sales_order_id,
                    'pos_order_id' => $return->pos_order_id,
                    'customer_id' => $return->customer_id,
                    'refund_net' => round($refundNet, 4),
                    'refund_tax' => round($refundTax, 4),
                    'refund_amount' => round($refundTotal, 4),
                    'refund_method' => $return->refund_method,
                    'invoice_code' => $invoice?->code,
                    'invoice_remaining' => $invoice ? round($invoice->balance(), 4) : null,
                    'line_count' => $return->lines->count(),
                ],
            ]);

            return $return->load('lines.product');
        });
    }

    /**
     * 解析来源原单，返回 [source, order, customerId, warehouseId, locationId]。
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: SalesOrder|PosOrder, 2: ?int, 3: int, 4: ?int}
     */
    private function resolveSource(array $data): array
    {
        $source = $data['source_type'] ?? '';

        if ($source === SalesReturn::SOURCE_SALES_ORDER) {
            $order = SalesOrder::findOrFail($data['sales_order_id']);
            if ($order->status !== SalesOrder::STATUS_SHIPPED) {
                throw new InventoryException("销售单 {$order->code} 非已发货状态，不能退货");
            }

            return [$source, $order, $order->customer_id, (int) $order->warehouse_id, null];
        }

        if ($source === SalesReturn::SOURCE_POS_ORDER) {
            $order = PosOrder::with('station')->findOrFail($data['pos_order_id']);
            if (! $order->isCompleted()) {
                throw new InventoryException("POS 订单 {$order->code} 非已完成状态，不能退货");
            }
            $station = $order->station;

            return [$source, $order, $order->customer_id, (int) ($station?->warehouse_id ?? 0), $station?->default_location_id];
        }

        throw new InventoryException('退货来源类型无效');
    }

    /**
     * 解析退货行对应的原单行，返回 [originalLine, 折后单价净额, 单行税额]。
     *
     * @param  array<string, mixed>  $line
     * @return array{0: SalesOrderLine|PosOrderLine, 1: float, 2: float}
     */
    private function resolveLine(string $source, $order, array $line): array
    {
        if ($source === SalesReturn::SOURCE_SALES_ORDER) {
            $original = SalesOrderLine::findOrFail($line['sales_order_line_id']);
            if ((int) $original->order_id !== $order->id) {
                throw new InventoryException('退货行不属于该销售单');
            }
            $originalQty = (float) $original->qty;
            $netUnit = $originalQty > 0 ? (float) $original->subtotal / $originalQty : 0;
            $unitTax = $originalQty > 0 ? (float) $original->tax_amount / $originalQty : 0;

            return [$original, $netUnit, $unitTax];
        }

        $original = PosOrderLine::findOrFail($line['pos_order_line_id']);
        if ((int) $original->order_id !== $order->id) {
            throw new InventoryException('退货行不属于该 POS 订单');
        }
        $originalQty = (float) $original->qty;
        $gross = (float) $original->unit_price * $originalQty;
        $netAmount = $gross - (float) $original->discount_amount;
        $netUnit = $originalQty > 0 ? $netAmount / $originalQty : 0;
        $unitTax = $originalQty > 0 ? (float) $original->tax_amount / $originalQty : 0;

        return [$original, $netUnit, $unitTax];
    }

    /** 校验退货数量不超过原售未退数量。 */
    private function assertReturnable(string $source, $original, float $qty, int $excludeReturnId): void
    {
        $originalQty = (float) $original->qty;
        $returned = $this->returnedQty($source, $original->id, $excludeReturnId);

        if ($returned + $qty > $originalQty + 0.0000001) {
            throw new InventoryException(
                "商品 {$original->product?->code} 超退：原售 {$originalQty}，已退 {$returned}，本次 {$qty}"
            );
        }
    }

    /** 原单行累计已退数量（仅统计已过账退货单）。 */
    private function returnedQty(string $source, int $originalLineId, ?int $excludeReturnId = null): float
    {
        $query = SalesReturnLine::query()
            ->whereHas('salesReturn', fn ($q) => $q->where('status', SalesReturn::STATUS_DONE))
            ->when($excludeReturnId !== null,
                fn ($q) => $q->whereHas('salesReturn', fn ($r) => $r->where('id', '!=', $excludeReturnId)));

        $column = $source === SalesReturn::SOURCE_SALES_ORDER
            ? 'sales_order_line_id'
            : 'pos_order_line_id';

        return (float) $query->where($column, $originalLineId)->sum('qty');
    }

    /** 成本回库分录：借 库存商品 / 贷 主营业务成本（红字冲回 COGS）。 */
    private function postCogsReversal(SalesReturn $return, array $cogsLines): void
    {
        if ($cogsLines === []) {
            return;
        }

        $journal = Journal::where('code', 'MISC')->firstOrFail();
        $entry = $this->entries->createEntry([
            'journal_id' => $journal->id,
            'entry_date' => $return->return_date?->toDateString(),
            'source_type' => 'sales_return',
            'source_id' => $return->id,
            'source_code' => $return->code,
            'description' => "退货成本回库 {$return->code}",
        ], $cogsLines);

        $this->entries->post($entry);
    }

    /** 退款冲回分录：借 主营业务收入 + 销项税额 / 贷 应收（冲减部分）或资金/预收。 */
    private function postRefundEntry(SalesReturn $return, float $net, float $tax, float $invoiceApplied = 0.0): void
    {
        if ($net <= 0 && $tax <= 0) {
            return;
        }

        $creditAccount = self::REFUND_CREDIT_MAP[$return->refund_method] ?? '1003';
        $lines = [];

        if ($net > 0) {
            $lines[] = ['account_code' => '4001', 'direction' => 'debit', 'amount' => $net, 'memo' => '冲回主营业务收入'];
        }
        if ($tax > 0) {
            $lines[] = ['account_code' => '2004', 'direction' => 'debit', 'amount' => $tax, 'memo' => '冲回销项税额'];
        }

        $creditAmount = round($net + $tax, 4);

        if ($return->refund_method === SalesReturn::REFUND_CREDIT && $invoiceApplied > 0) {
            // 冲抵原挂账应收的金额记 1003，超出部分（客户预付）记 2005，避免应收出现负数。
            $arApplied = round(min($creditAmount, max(0.0, $invoiceApplied)), 4);
            if ($arApplied > 0) {
                $lines[] = [
                    'account_code' => '1003',
                    'direction' => 'credit',
                    'amount' => $arApplied,
                    'partner_id' => $return->customer_id,
                    'memo' => '冲减应收账款（退货）',
                ];
            }

            $prepaid = round($creditAmount - $arApplied, 4);
            if ($prepaid > 0) {
                $lines[] = [
                    'account_code' => '2005',
                    'direction' => 'credit',
                    'amount' => $prepaid,
                    'partner_id' => $return->customer_id,
                    'memo' => '退货转客户预收',
                ];
            }
        } else {
            $lines[] = [
                'account_code' => $creditAccount,
                'direction' => 'credit',
                'amount' => $creditAmount,
                'partner_id' => $return->customer_id,
                'memo' => '退款（' . ($return->refund_method ?? 'credit') . '）',
            ];
        }

        $journal = Journal::where('code', 'SALE')->firstOrFail();
        $entry = $this->entries->createEntry([
            'journal_id' => $journal->id,
            'entry_date' => $return->return_date?->toDateString(),
            'source_type' => 'sales_return',
            'source_id' => $return->id,
            'source_code' => $return->code,
            'description' => "销售退款冲回 {$return->code}",
        ], $lines);

        $this->entries->post($entry);
    }

    /**
     * 挂账退款冲减原订单的应收发票：返回 [发票, 实际冲减金额]。
     * 仅 credit 退款方式处理；无发票或已无余额时返回 [null, 0]。
     *
     * @return array{0: ?Invoice, 1: float}
     */
    private function applyRefundToInvoice(SalesReturn $return, float $refundTotal): array
    {
        if ($return->refund_method !== SalesReturn::REFUND_CREDIT || $refundTotal <= 0) {
            return [null, 0.0];
        }

        [$sourceType, $sourceId] = $return->source_type === SalesReturn::SOURCE_POS_ORDER
            ? ['pos_order', $return->pos_order_id]
            : ['sales_order', $return->sales_order_id];

        if (! $sourceId) {
            return [null, 0.0];
        }

        $invoice = Invoice::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIAL])
            ->orderBy('id')
            ->first();

        if (! $invoice) {
            return [null, 0.0];
        }

        $balance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 4);
        if ($balance <= 0) {
            return [$invoice, 0.0];
        }

        $applied = round(min($balance, $refundTotal), 4);
        $invoice->total_amount = round((float) $invoice->total_amount - $applied, 4);
        $invoice->recalcStatus();

        if ((float) $invoice->total_amount <= 0.0001 && (float) $invoice->paid_amount <= 0.0001) {
            $invoice->status = Invoice::STATUS_VOID;
        }

        $invoice->save();

        return [$invoice, $applied];
    }

    /** 整单退完时回写原单状态。 */
    private function syncSourceOrder(SalesReturn $return): void
    {
        if ($return->source_type === SalesReturn::SOURCE_SALES_ORDER && $return->sales_order_id) {
            $order = SalesOrder::find($return->sales_order_id);
            if ($order && $this->allReturnedSales($order)) {
                $order->status = SalesOrder::STATUS_RETURNED;
                $order->save();
            }

            return;
        }

        if ($return->source_type === SalesReturn::SOURCE_POS_ORDER && $return->pos_order_id) {
            $order = PosOrder::find($return->pos_order_id);
            if ($order && $this->allReturnedPos($order)) {
                $order->status = PosOrder::STATUS_REFUNDED;
                $order->save();
            }
        }
    }

    private function allReturnedSales(SalesOrder $order): bool
    {
        foreach ($order->lines as $line) {
            $returned = $this->returnedQty(SalesReturn::SOURCE_SALES_ORDER, $line->id, null);
            if ($returned < (float) $line->qty - 0.0000001) {
                return false;
            }
        }

        return true;
    }

    private function allReturnedPos(PosOrder $order): bool
    {
        foreach ($order->lines as $line) {
            $returned = $this->returnedQty(SalesReturn::SOURCE_POS_ORDER, $line->id, null);
            if ($returned < (float) $line->qty - 0.0000001) {
                return false;
            }
        }

        return true;
    }
}
