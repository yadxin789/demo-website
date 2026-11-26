<?php

namespace App\Modules\Shop\Services\Accounting;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Models\AccountEntry;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Bill;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\Payment;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 收付款与核销服务：登记收款/付款，按应收发票或应付账单核销，
 * 回写往来单据已收/已付金额与状态，生成资金分录并过账。
 */
class PaymentService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected EntryService $entries,
        protected AuditLogger $logger
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $allocations  [{invoice_id?|bill_id?, amount}]
     */
    public function register(array $data, array $allocations): Payment
    {
        if ($allocations === []) {
            throw new AccountingException('收付款必须至少核销一条单据');
        }

        $direction = $data['direction'] ?? '';
        if (! in_array($direction, [Payment::DIRECTION_RECEIVE, Payment::DIRECTION_PAY], true)) {
            throw new AccountingException('收付款方向必须为收款或付款');
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new AccountingException('收付款金额必须大于 0');
        }

        return DB::transaction(function () use ($data, $allocations, $direction, $amount) {
            $payment = Payment::create([
                'code' => $this->sequences->generate('payment'),
                'partner_id' => (int) $data['partner_id'],
                'direction' => $direction,
                'method_id' => (int) $data['method_id'],
                'account_id' => (int) $data['account_id'],
                'amount' => $amount,
                'payment_date' => isset($data['payment_date']) ? Carbon::parse($data['payment_date']) : Carbon::today(),
                'reference' => $data['reference'] ?? null,
                'status' => Payment::STATUS_CONFIRMED,
                'operator_id' => $data['operator_id'] ?? null,
            ]);

            $allocated = 0.0;

            foreach ($allocations as $row) {
                $allocAmount = (float) ($row['amount'] ?? 0);
                if ($allocAmount <= 0) {
                    throw new AccountingException('核销金额必须大于 0');
                }

                if ($direction === Payment::DIRECTION_RECEIVE) {
                    $invoice = Invoice::find($row['invoice_id'] ?? null);
                    if (! $invoice || $invoice->isVoid()) {
                        throw new AccountingException('应收发票不存在或已作废，不能收款核销');
                    }
                    if ($allocAmount > $invoice->balance() + 0.0001) {
                        throw new AccountingException("收款超额：发票 {$invoice->code} 余额 {$invoice->balance()}");
                    }

                    $oldPaid = (float) $invoice->paid_amount;
                    $oldStatus = $invoice->status;
                    $invoice->paid_amount = $oldPaid + $allocAmount;
                    $invoice->recalcStatus();
                    $invoice->save();

                    $this->logger->record([
                        'module' => 'finance',
                        'action' => AuditLog::ACTION_UPDATE,
                        'model' => Invoice::class,
                        'record_id' => $invoice->id,
                        'document_no' => $invoice->code,
                        'field' => 'paid_amount',
                        'old_value' => $oldPaid,
                        'new_value' => (float) $invoice->paid_amount,
                        'context' => [
                            'alloc_amount' => $allocAmount,
                            'old_status' => $oldStatus,
                            'new_status' => $invoice->status,
                            'payment_code' => $payment->code,
                        ],
                    ]);

                    $payment->allocations()->create([
                        'invoice_id' => $invoice->id,
                        'bill_id' => null,
                        'amount' => $allocAmount,
                    ]);
                } else {
                    $bill = Bill::find($row['bill_id'] ?? null);
                    if (! $bill || $bill->isVoid()) {
                        throw new AccountingException('应付账单不存在或已作废，不能付款核销');
                    }
                    if ($allocAmount > $bill->balance() + 0.0001) {
                        throw new AccountingException("付款超额：账单 {$bill->code} 余额 {$bill->balance()}");
                    }

                    $oldPaid = (float) $bill->paid_amount;
                    $oldStatus = $bill->status;
                    $bill->paid_amount = $oldPaid + $allocAmount;
                    $bill->recalcStatus();
                    $bill->save();

                    $this->logger->record([
                        'module' => 'finance',
                        'action' => AuditLog::ACTION_UPDATE,
                        'model' => Bill::class,
                        'record_id' => $bill->id,
                        'document_no' => $bill->code,
                        'field' => 'paid_amount',
                        'old_value' => $oldPaid,
                        'new_value' => (float) $bill->paid_amount,
                        'context' => [
                            'alloc_amount' => $allocAmount,
                            'old_status' => $oldStatus,
                            'new_status' => $bill->status,
                            'payment_code' => $payment->code,
                        ],
                    ]);

                    $payment->allocations()->create([
                        'invoice_id' => null,
                        'bill_id' => $bill->id,
                        'amount' => $allocAmount,
                    ]);
                }

                $allocated += $allocAmount;
            }

            if (abs($allocated - $amount) > 0.0001) {
                throw new AccountingException(sprintf('核销合计 %.4f 与收付款金额 %.4f 不一致', $allocated, $amount));
            }

            $entry = $this->buildEntry($payment);
            $payment->entry_id = $entry->id;
            $payment->save();

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_CREATE,
                'model' => Payment::class,
                'record_id' => $payment->id,
                'document_no' => $payment->code,
                'message' => $direction === Payment::DIRECTION_RECEIVE ? "收款 {$payment->code}" : "付款 {$payment->code}",
                'context' => [
                    'direction' => $direction,
                    'partner_id' => $payment->partner_id,
                    'amount' => $amount,
                    'allocated' => $allocated,
                ],
            ]);

            return $payment->load('allocations');
        });
    }

    private function buildEntry(Payment $payment): AccountEntry
    {
        $account = $payment->account;
        $journal = $this->journalFor((string) $account?->code);

        if ($payment->isReceive()) {
            // 借：资金科目 / 贷：应收账款
            $lines = [
                ['account_id' => $account->id, 'direction' => 'debit', 'amount' => (float) $payment->amount, 'memo' => '收款入账'],
                ['account_code' => '1003', 'direction' => 'credit', 'amount' => (float) $payment->amount, 'memo' => '应收账款'],
            ];
        } else {
            // 借：应付账款 / 贷：资金科目
            $lines = [
                ['account_code' => '2001', 'direction' => 'debit', 'amount' => (float) $payment->amount, 'memo' => '应付账款'],
                ['account_id' => $account->id, 'direction' => 'credit', 'amount' => (float) $payment->amount, 'memo' => '付款出账'],
            ];
        }

        $entry = $this->entries->createEntry([
            'journal_id' => $journal->id,
            'entry_date' => $payment->payment_date?->toDateString(),
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'source_code' => $payment->code,
            'description' => $payment->reference ?? ($payment->isReceive() ? '收款' : '付款'),
        ], $lines);

        return $this->entries->post($entry);
    }

    private function journalFor(string $accountCode): Journal
    {
        $code = match ($accountCode) {
            '1004' => 'CASH',
            '1005' => 'BANK',
            default => 'MISC',
        };

        return Journal::where('code', $code)->firstOrFail();
    }
}
