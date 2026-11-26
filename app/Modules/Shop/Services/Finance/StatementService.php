<?php

namespace App\Modules\Shop\Services\Finance;

use App\Modules\Shop\Models\Bill;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 客户对账服务：按客户与期间汇总应收发票、已收款项与应收余额。
 * 余额 = 期间发票应收合计 - 期间已收款项合计；作废发票与草稿收款不计入。
 */
class StatementService
{
    /**
     * @return array{
     *   rows: Collection<int, array<string, mixed>>,
     *   invoiceTotal: float,
     *   paymentTotal: float,
     *   balance: float
     * }
     */
    public function statement(?int $customerId, ?Carbon $start, ?Carbon $end): array
    {
        $invoices = Invoice::query()
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->with('partner')
            ->when($customerId !== null, fn ($q) => $q->where('partner_id', $customerId))
            ->when($start !== null, fn ($q) => $q->whereDate('invoice_date', '>=', $start->toDateString()))
            ->when($end !== null, fn ($q) => $q->whereDate('invoice_date', '<=', $end->toDateString()))
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $payments = Payment::query()
            ->where('direction', Payment::DIRECTION_RECEIVE)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->when($customerId !== null, fn ($q) => $q->where('partner_id', $customerId))
            ->when($start !== null, fn ($q) => $q->whereDate('payment_date', '>=', $start->toDateString()))
            ->when($end !== null, fn ($q) => $q->whereDate('payment_date', '<=', $end->toDateString()))
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $invoiceTotal = round($invoices->sum('total_amount'), 4);
        $paymentTotal = round($payments->sum('amount'), 4);
        $balance = round($invoiceTotal - $paymentTotal, 4);

        $rows = collect();

        foreach ($invoices as $invoice) {
            $rows->push([
                'date' => optional($invoice->invoice_date)->toDateString(),
                'type' => 'invoice',
                'code' => $invoice->code,
                'debit' => (float) $invoice->total_amount,
                'credit' => 0.0,
                'status' => $invoice->status,
                'memo' => '应收发票',
            ]);
        }

        foreach ($payments as $payment) {
            $rows->push([
                'date' => optional($payment->payment_date)->toDateString(),
                'type' => 'payment',
                'code' => $payment->code,
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
                'status' => $payment->status,
                'memo' => $payment->reference ?: '收款',
            ]);
        }

        $rows = $rows->sortBy(fn ($row) => $row['date'] ?? '')->values();

        return compact('rows', 'invoiceTotal', 'paymentTotal', 'balance');
    }

    /**
     * 供应商对账单：期间应付账单增加应付，已付款项减少应付。
     * 余额 = 期间账单应付合计 - 期间已付款项合计；草稿/作废账单与草稿付款不计入。
     *
     * @return array{
     *   rows: Collection<int, array<string, mixed>>,
     *   invoiceTotal: float,
     *   paymentTotal: float,
     *   balance: float
     * }
     */
    public function supplierStatement(?int $supplierId, ?Carbon $start, ?Carbon $end): array
    {
        $bills = Bill::query()
            ->whereNotIn('status', [Bill::STATUS_DRAFT, Bill::STATUS_VOID])
            ->with('partner')
            ->when($supplierId !== null, fn ($q) => $q->where('partner_id', $supplierId))
            ->when($start !== null, fn ($q) => $q->whereDate('bill_date', '>=', $start->toDateString()))
            ->when($end !== null, fn ($q) => $q->whereDate('bill_date', '<=', $end->toDateString()))
            ->orderBy('bill_date')
            ->orderBy('id')
            ->get();

        $payments = Payment::query()
            ->where('direction', Payment::DIRECTION_PAY)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->when($supplierId !== null, fn ($q) => $q->where('partner_id', $supplierId))
            ->when($start !== null, fn ($q) => $q->whereDate('payment_date', '>=', $start->toDateString()))
            ->when($end !== null, fn ($q) => $q->whereDate('payment_date', '<=', $end->toDateString()))
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $invoiceTotal = round($bills->sum('total_amount'), 4);
        $paymentTotal = round($payments->sum('amount'), 4);
        $balance = round($invoiceTotal - $paymentTotal, 4);

        $rows = collect();

        foreach ($bills as $bill) {
            $rows->push([
                'date' => optional($bill->bill_date)->toDateString(),
                'type' => 'bill',
                'code' => $bill->code,
                'debit' => 0.0,
                'credit' => (float) $bill->total_amount,
                'status' => $bill->status,
                'memo' => '应付账单' . ($bill->source_type ? "（{$bill->source_type}）" : ''),
            ]);
        }

        foreach ($payments as $payment) {
            $rows->push([
                'date' => optional($payment->payment_date)->toDateString(),
                'type' => 'payment',
                'code' => $payment->code,
                'debit' => (float) $payment->amount,
                'credit' => 0.0,
                'status' => $payment->status,
                'memo' => $payment->reference ?: '付款',
            ]);
        }

        $rows = $rows->sortBy(fn ($row) => $row['date'] ?? '')->values();

        return compact('rows', 'invoiceTotal', 'paymentTotal', 'balance');
    }
}
