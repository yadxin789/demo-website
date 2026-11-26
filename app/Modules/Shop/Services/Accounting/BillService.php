<?php

namespace App\Modules\Shop\Services\Accounting;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Bill;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\PurchaseOrderLine;
use App\Modules\Shop\Models\ReceiptLine;
use App\Modules\Shop\Models\Receipt;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 供应商账单服务：登记应付账单，按采购订单 / 收货入库 / 账单三单行级匹配，
 * 数量金额一致才允许过账（差异拦截）。
 */
class BillService
{
    private const TOLERANCE = 0.0001;

    public function __construct(
        protected SequenceGenerator $sequences,
        protected EntryService $entries,
        protected AuditLogger $logger
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines  [{purchase_order_line_id, product_id, qty, unit_price, tax_amount?}]
     */
    public function register(array $data, array $lines): Bill
    {
        if ($lines === []) {
            throw new AccountingException('账单至少需要一行明细');
        }

        return DB::transaction(function () use ($data, $lines) {
            $bill = Bill::create([
                'code' => $this->sequences->generate('bill'),
                'partner_id' => (int) $data['partner_id'],
                'bill_date' => isset($data['bill_date']) ? Carbon::parse($data['bill_date']) : Carbon::today(),
                'due_date' => isset($data['due_date']) && $data['due_date'] !== '' ? Carbon::parse($data['due_date']) : null,
                'status' => Bill::STATUS_DRAFT,
                'total_amount' => 0,
                'paid_amount' => 0,
                'source_type' => $data['source_type'] ?? 'purchase_order',
                'source_id' => $data['source_id'] ?? null,
            ]);

            $total = 0.0;

            foreach ($lines as $line) {
                $qty = (float) ($line['qty'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                if ($qty <= 0 || $unitPrice < 0) {
                    throw new AccountingException('账单行数量必须大于 0，单价不能为负');
                }

                $amount = round($qty * $unitPrice, 4);
                $taxAmount = round((float) ($line['tax_amount'] ?? 0), 4);

                $bill->lines()->create([
                    'purchase_order_line_id' => $line['purchase_order_line_id'] ?? null,
                    'product_id' => (int) $line['product_id'],
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                    'tax_amount' => $taxAmount,
                    'line_note' => $line['line_note'] ?? null,
                ]);

                $total += $amount;
            }

            $bill->total_amount = round($total, 4);
            $bill->save();

            return $bill->load('lines.product');
        });
    }

    /**
     * 三单匹配：订单 / 入库 / 账单 数量与金额行级比对。
     *
     * @return array{matches: bool, differences: array<int, array<string, mixed>>}
     */
    public function match(Bill $bill): array
    {
        $differences = [];

        foreach ($bill->lines as $line) {
            $poLine = $line->purchaseOrderLine;
            $received = ReceiptLine::where('purchase_order_line_id', $line->purchase_order_line_id)
                ->whereHas('receipt', fn ($query) => $query->where('status', Receipt::STATUS_DONE))
                ->get();

            $recvQty = (float) $received->sum('qty');
            $recvAmt = (float) $received->sum(fn ($r) => (float) $r->qty * (float) $r->unit_cost);

            $orderQty = $poLine ? (float) $poLine->qty : null;
            $orderAmt = $poLine ? (float) $poLine->subtotal : null;

            if ($orderQty !== null && abs($line->qty - $orderQty) > self::TOLERANCE) {
                $differences[] = [
                    'type' => 'qty', 'product_id' => $line->product_id,
                    'bill' => $line->qty, 'order' => $orderQty, 'received' => $recvQty,
                ];
            }

            if (abs($line->amount - $recvAmt) > self::TOLERANCE) {
                $differences[] = [
                    'type' => 'amount', 'product_id' => $line->product_id,
                    'bill' => $line->amount, 'received' => $recvAmt,
                    'order' => $orderAmt,
                ];
            }
        }

        return ['matches' => $differences === [], 'differences' => $differences];
    }

    /**
     * 确认账单：三单一致后冲回应付暂估、登记进项税与应付账款，并过账分录。
     */
    public function confirm(Bill $bill): Bill
    {
        if ($bill->status !== Bill::STATUS_DRAFT) {
            throw new AccountingException("账单 {$bill->code} 非草稿状态，不能确认");
        }

        $this->assertMatchable($bill);

        return DB::transaction(function () use ($bill) {
            $bill->load('lines');

            $net = round((float) $bill->total_amount, 4);
            $tax = round((float) $bill->lines->sum('tax_amount'), 4);
            $payable = round($net + $tax, 4);

            $entryLines = [
                ['account_code' => '2002', 'direction' => 'debit', 'amount' => $net, 'memo' => '冲回应付暂估'],
            ];
            if ($tax > 0) {
                $entryLines[] = ['account_code' => '2003', 'direction' => 'debit', 'amount' => $tax, 'memo' => '进项税额'];
            }
            $entryLines[] = ['account_code' => '2001', 'direction' => 'credit', 'amount' => $payable, 'memo' => '应付账款'];

            $journal = Journal::where('code', 'PURCHASE')->firstOrFail();
            $entry = $this->entries->createEntry([
                'journal_id' => $journal->id,
                'entry_date' => $bill->bill_date?->toDateString(),
                'source_type' => 'bill',
                'source_id' => $bill->id,
                'source_code' => $bill->code,
                'description' => "到票确认 {$bill->code}",
            ], $entryLines);
            $this->entries->post($entry);

            $bill->entry_id = $entry->id;
            $bill->status = Bill::STATUS_OPEN;
            $bill->save();

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_APPROVE,
                'model' => Bill::class,
                'record_id' => $bill->id,
                'document_no' => $bill->code,
                'message' => "账单 {$bill->code} 到票确认，挂应付 {$payable}",
                'context' => ['net' => $net, 'tax' => $tax, 'payable' => $payable, 'entry_id' => $entry->id],
            ]);

            return $bill->load('lines');
        });
    }

    /** 过账前校验三单一致；存在差异则拦截。 */
    public function assertMatchable(Bill $bill): void
    {
        $result = $this->match($bill);

        if (! $result['matches']) {
            throw new AccountingException(sprintf(
                '账单 %s 三单匹配存在 %d 处差异，不能过账',
                $bill->code,
                count($result['differences'])
            ));
        }
    }
}
