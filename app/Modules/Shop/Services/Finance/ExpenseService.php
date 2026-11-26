<?php

namespace App\Modules\Shop\Services\Finance;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Models\AccountEntry;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Expense;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 费用报销服务：报销单录入、确认生成费用凭证、挂账后付款核销。
 * 挂账（payable）确认时贷记 2006 其他应付款，付款时借记 2006；
 * 现金/银行即时结算确认时直接贷记资金科目并置为已付款。
 */
class ExpenseService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected EntryService $entries,
        protected AuditLogger $logger
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createExpense(array $data, array $lines): Expense
    {
        if ($lines === []) {
            throw new AccountingException('报销单至少需要一行费用明细');
        }

        $method = in_array($data['settlement_method'] ?? '', [
            Expense::SETTLE_PAYABLE, Expense::SETTLE_CASH, Expense::SETTLE_BANK,
        ], true) ? $data['settlement_method'] : Expense::SETTLE_PAYABLE;

        return DB::transaction(function () use ($data, $lines, $method) {
            $net = 0.0;
            $tax = 0.0;

            $payload = [];
            foreach (array_values($lines) as $index => $line) {
                $amountNet = round((float) ($line['amount_net'] ?? 0), 4);
                $taxAmount = round((float) ($line['tax_amount'] ?? 0), 4);
                $total = round($amountNet + $taxAmount, 4);

                if ($amountNet <= 0) {
                    throw new AccountingException('费用明细不含税金额必须大于 0');
                }

                $net += $amountNet;
                $tax += $taxAmount;

                $payload[] = [
                    'description' => (string) ($line['description'] ?? '费用报销'),
                    'amount_net' => $amountNet,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $total,
                    'sort' => $index,
                ];
            }

            $expense = Expense::create([
                'code' => $this->sequences->generate('expense'),
                'expense_date' => isset($data['expense_date']) ? Carbon::parse($data['expense_date']) : Carbon::today(),
                'expense_account_id' => (int) $data['expense_account_id'],
                'partner_id' => $data['partner_id'] ?? null,
                'payee' => $data['payee'] ?? null,
                'department' => $data['department'] ?? null,
                'settlement_method' => $method,
                'payment_account_id' => $method === Expense::SETTLE_PAYABLE ? null : ($data['payment_account_id'] ?? null),
                'amount_net' => round($net, 4),
                'tax_amount' => round($tax, 4),
                'total_amount' => round($net + $tax, 4),
                'status' => Expense::STATUS_DRAFT,
                'description' => $data['description'] ?? null,
                'reference' => $data['reference'] ?? null,
                'creator_id' => $data['creator_id'] ?? null,
            ]);

            foreach ($payload as $row) {
                $expense->lines()->create($row);
            }

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_CREATE,
                'model' => Expense::class,
                'record_id' => $expense->id,
                'document_no' => $expense->code,
                'message' => "费用报销单 {$expense->code} 草稿创建",
                'context' => [
                    'total_amount' => (float) $expense->total_amount,
                    'settlement_method' => $method,
                    'line_count' => count($payload),
                ],
            ]);

            return $expense->load('lines');
        });
    }

    /** 确认报销：生成并过账费用凭证；现金/银行即时结算同时置为已付款。 */
    public function confirm(Expense $expense): Expense
    {
        if ($expense->status !== Expense::STATUS_DRAFT) {
            throw new AccountingException("报销单 {$expense->code} 非草稿状态，不能确认");
        }

        return DB::transaction(function () use ($expense) {
            $account = $expense->expenseAccount;
            if (! $account) {
                throw new AccountingException('报销单未指定费用科目');
            }

            $total = round((float) $expense->total_amount, 4);
            $net = round((float) $expense->amount_net, 4);
            $tax = round((float) $expense->tax_amount, 4);

            $lines = [
                ['account_id' => $account->id, 'direction' => 'debit', 'amount' => $net > 0 ? $net : $total, 'memo' => '费用报销'],
            ];
            if ($tax > 0) {
                $lines[] = ['account_code' => '2003', 'direction' => 'debit', 'amount' => $tax, 'memo' => '进项税额'];
            }

            if ($expense->settlement_method === Expense::SETTLE_PAYABLE) {
                $creditCode = '2006';
                $creditAccountId = null;
                $journalCode = 'MISC';
            } else {
                $payment = $expense->paymentAccount;
                if (! $payment) {
                    throw new AccountingException('现金/银行结算必须指定付款科目');
                }
                $creditAccountId = $payment->id;
                $creditCode = null;
                $journalCode = $payment->code === '1004' ? 'CASH' : 'BANK';
            }

            $lines[] = [
                'account_code' => $creditCode,
                'account_id' => $creditAccountId,
                'direction' => 'credit',
                'amount' => $total,
                'memo' => $expense->settlement_method === Expense::SETTLE_PAYABLE ? '其他应付款' : '报销付款',
            ];

            $journal = Journal::where('code', $journalCode)->firstOrFail();
            $entry = $this->entries->createEntry([
                'journal_id' => $journal->id,
                'entry_date' => $expense->expense_date?->toDateString(),
                'source_type' => 'expense',
                'source_id' => $expense->id,
                'source_code' => $expense->code,
                'description' => $expense->description ?: "费用报销 {$expense->code}",
                'creator_id' => $expense->creator_id,
            ], $lines);

            $entry = $this->entries->post($entry);
            $expense->entry_id = $entry->id;

            if ($expense->settlement_method === Expense::SETTLE_PAYABLE) {
                $expense->status = Expense::STATUS_CONFIRMED;
            } else {
                $expense->status = Expense::STATUS_PAID;
                $expense->paid_entry_id = $entry->id;
                $expense->paid_at = Carbon::now();
            }
            $expense->save();

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_APPROVE,
                'model' => Expense::class,
                'record_id' => $expense->id,
                'document_no' => $expense->code,
                'message' => "费用报销单 {$expense->code} 确认入账",
                'context' => [
                    'entry_code' => $entry->code,
                    'total_amount' => $total,
                    'settlement_method' => $expense->settlement_method,
                ],
            ]);

            return $expense->load('lines');
        });
    }

    /** 挂账报销付款：借 2006 其他应付款 / 贷 资金科目；重复付款拦截。 */
    public function pay(Expense $expense, ?int $paymentAccountId = null): Expense
    {
        if ($expense->settlement_method !== Expense::SETTLE_PAYABLE) {
            throw new AccountingException('仅挂账报销需要单独付款');
        }

        if ($expense->status === Expense::STATUS_PAID) {
            throw new AccountingException("报销单 {$expense->code} 已付款，不能重复付款");
        }

        if ($expense->status !== Expense::STATUS_CONFIRMED) {
            throw new AccountingException("报销单 {$expense->code} 未确认入账，不能付款");
        }

        return DB::transaction(function () use ($expense, $paymentAccountId) {
            $accountId = $paymentAccountId ?: (int) $expense->payment_account_id;
            $payment = $accountId ? \App\Modules\Shop\Models\Account::find($accountId) : null;
            if (! $payment) {
                throw new AccountingException('付款必须指定资金科目');
            }

            $total = round((float) $expense->total_amount, 4);
            $journal = Journal::where('code', $payment->code === '1004' ? 'CASH' : 'BANK')->firstOrFail();

            $entry = $this->entries->createEntry([
                'journal_id' => $journal->id,
                'entry_date' => Carbon::today()->toDateString(),
                'source_type' => 'expense_payment',
                'source_id' => $expense->id,
                'source_code' => $expense->code,
                'description' => "报销付款 {$expense->code}",
            ], [
                ['account_code' => '2006', 'direction' => 'debit', 'amount' => $total, 'memo' => '其他应付款核销'],
                ['account_id' => $payment->id, 'direction' => 'credit', 'amount' => $total, 'memo' => '报销付款'],
            ]);

            $entry = $this->entries->post($entry);
            $expense->paid_entry_id = $entry->id;
            $expense->payment_account_id = $payment->id;
            $expense->status = Expense::STATUS_PAID;
            $expense->paid_at = Carbon::now();
            $expense->save();

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_APPROVE,
                'model' => Expense::class,
                'record_id' => $expense->id,
                'document_no' => $expense->code,
                'message' => "报销单 {$expense->code} 付款完成",
                'context' => [
                    'entry_code' => $entry->code,
                    'payment_account' => $payment->code,
                    'total_amount' => $total,
                ],
            ]);

            return $expense->load('lines');
        });
    }

    /** 作废报销单：已入账单据先冲销凭证。 */
    public function void(Expense $expense): Expense
    {
        if ($expense->status === Expense::STATUS_VOID) {
            throw new AccountingException("报销单 {$expense->code} 已作废");
        }

        return DB::transaction(function () use ($expense) {
            if ($expense->entry_id) {
                $entry = AccountEntry::find($expense->entry_id);
                if ($entry && $entry->status === AccountEntry::STATUS_POSTED) {
                    $this->entries->reverse($entry);
                }
            }
            if ($expense->paid_entry_id && $expense->paid_entry_id !== $expense->entry_id) {
                $paidEntry = AccountEntry::find($expense->paid_entry_id);
                if ($paidEntry && $paidEntry->status === AccountEntry::STATUS_POSTED) {
                    $this->entries->reverse($paidEntry);
                }
            }

            $expense->status = Expense::STATUS_VOID;
            $expense->save();

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_UPDATE,
                'model' => Expense::class,
                'record_id' => $expense->id,
                'document_no' => $expense->code,
                'message' => "费用报销单 {$expense->code} 作废",
            ]);

            return $expense;
        });
    }
}
