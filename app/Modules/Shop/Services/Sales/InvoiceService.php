<?php

namespace App\Modules\Shop\Services\Sales;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * 销售开票服务：基于已发货销售订单开立应收发票，
 * 生成 借应收账款 / 贷主营业务收入 + 销项税额 分录并过账，应收挂客户。
 */
class InvoiceService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected EntryService $entries,
        protected AuditLogger $logger
    ) {
    }

    public function issue(SalesOrder $order): Invoice
    {
        if ($order->status !== SalesOrder::STATUS_SHIPPED) {
            throw new AccountingException("销售单 {$order->code} 非已发货状态，不能开票");
        }

        return DB::transaction(function () use ($order) {
            $net = round((float) $order->total_amount, 4);
            $tax = round((float) $order->tax_amount, 4);
            $receivable = round((float) $order->grand_total, 4);

            $invoice = Invoice::create([
                'code' => $this->sequences->generate('invoice'),
                'partner_id' => $order->customer_id,
                'invoice_date' => $order->order_date?->toDateString(),
                'status' => Invoice::STATUS_OPEN,
                'total_amount' => $receivable,
                'paid_amount' => 0,
                'source_type' => 'sales_order',
                'source_id' => $order->id,
            ]);

            $entryLines = [
                ['account_code' => '1003', 'direction' => 'debit', 'amount' => $receivable, 'partner_id' => $order->customer_id, 'memo' => '应收账款'],
                ['account_code' => '4001', 'direction' => 'credit', 'amount' => $net, 'memo' => '主营业务收入'],
            ];
            if ($tax > 0) {
                $entryLines[] = ['account_code' => '2004', 'direction' => 'credit', 'amount' => $tax, 'memo' => '销项税额'];
            }

            $journal = Journal::where('code', 'SALE')->firstOrFail();
            $entry = $this->entries->createEntry([
                'journal_id' => $journal->id,
                'entry_date' => $invoice->invoice_date?->toDateString(),
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'source_code' => $invoice->code,
                'description' => "销售开票 {$order->code}",
            ], $entryLines);
            $this->entries->post($entry);

            $invoice->entry_id = $entry->id;
            $invoice->save();

            $this->logger->record([
                'module' => 'sales',
                'action' => AuditLog::ACTION_APPROVE,
                'model' => Invoice::class,
                'record_id' => $invoice->id,
                'document_no' => $invoice->code,
                'message' => "发票 {$invoice->code} 开立，应收 {$receivable}",
                'context' => ['net' => $net, 'tax' => $tax, 'receivable' => $receivable, 'sales_order_id' => $order->id],
            ]);

            return $invoice->load('entry.lines');
        });
    }
}
