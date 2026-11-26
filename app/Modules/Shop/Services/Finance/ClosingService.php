<?php

namespace App\Modules\Shop\Services\Finance;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Models\Account;
use App\Modules\Shop\Models\AccountEntry;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\FiscalPeriod;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 期末结账服务：关闭会计期间。
 * 结账前检查期间内是否存在未过账（草稿）凭证，存在则阻止关闭；
 * 已关闭期间不可再过账（由 EntryService 拦截）。结账时自动生成损益结转凭证：
 * 收入类科目借方、费用类科目贷方，差额转入 3002 本年利润；重复结转会被拦截。
 */
class ClosingService
{
    public function __construct(
        protected AuditLogger $logger,
        protected EntryService $entries
    ) {
    }

    /** 期间内未过账（草稿）凭证数量。 */
    public function draftCount(FiscalPeriod $period): int
    {
        return AccountEntry::query()
            ->where('status', AccountEntry::STATUS_DRAFT)
            ->whereDate('entry_date', '>=', $period->start_date->toDateString())
            ->whereDate('entry_date', '<=', $period->end_date->toDateString())
            ->count();
    }

    /** 全部期间及其未过账笔数、可否结账。 */
    public function periods(): Collection
    {
        return FiscalPeriod::orderBy('start_date')->get()->map(function (FiscalPeriod $period) {
            $draftCount = $this->draftCount($period);

            return [
                'period' => $period,
                'draftCount' => $draftCount,
                'canClose' => ! $period->isClosed() && $draftCount === 0,
            ];
        });
    }

    /** 关闭期间；存在未过账凭证或已关闭时拒绝。 */
    public function closePeriod(int $periodId, ?int $operatorId): FiscalPeriod
    {
        $period = FiscalPeriod::find($periodId);

        if (! $period) {
            throw new AccountingException('会计期间不存在');
        }

        if ($period->isClosed()) {
            throw new AccountingException("期间 {$period->name} 已关闭");
        }

        $draftCount = $this->draftCount($period);
        if ($draftCount > 0) {
            throw new AccountingException("期间 {$period->name} 尚有 {$draftCount} 张未过账凭证，不能结账");
        }

        return DB::transaction(function () use ($period, $operatorId) {
            $entry = $this->closeProfitLoss($period);

            $period->status = FiscalPeriod::STATUS_CLOSED;
            $period->closed_at = Carbon::now();
            $period->save();

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_APPROVE,
                'model' => FiscalPeriod::class,
                'record_id' => $period->id,
                'document_no' => $period->name,
                'message' => "会计期间 {$period->name} 结账关闭",
                'context' => [
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'profit_loss_entry' => $entry?->code,
                    'operator_id' => $operatorId,
                ],
            ]);

            return $period;
        });
    }

    /**
     * 生成期间损益结转凭证：收入类借方、费用类贷方，净额转入 3002 本年利润。
     * 期间无损益发生额时不生成凭证。
     */
    private function closeProfitLoss(FiscalPeriod $period): ?\App\Modules\Shop\Models\AccountEntry
    {
        $existing = AccountEntry::query()
            ->where('source_type', 'period_close')
            ->where('source_id', $period->id)
            ->where('status', AccountEntry::STATUS_POSTED)
            ->exists();

        if ($existing) {
            throw new AccountingException("期间 {$period->name} 已生成损益结转凭证，不能重复结账");
        }

        $accounts = Account::query()
            ->whereIn('type', [Account::TYPE_INCOME, Account::TYPE_EXPENSE])
            ->get()
            ->keyBy('id');

        if ($accounts->isEmpty()) {
            return null;
        }

        $sums = DB::table('account_entry_lines')
            ->join('account_entries', 'account_entries.id', '=', 'account_entry_lines.entry_id')
            ->where('account_entries.status', AccountEntry::STATUS_POSTED)
            ->whereDate('account_entries.entry_date', '>=', $period->start_date->toDateString())
            ->whereDate('account_entries.entry_date', '<=', $period->end_date->toDateString())
            ->whereIn('account_entry_lines.account_id', $accounts->keys())
            ->groupBy('account_entry_lines.account_id', 'account_entry_lines.direction')
            ->selectRaw('account_entry_lines.account_id, account_entry_lines.direction, SUM(account_entry_lines.amount) as total')
            ->get();

        $balances = [];
        foreach ($sums as $row) {
            $id = (int) $row->account_id;
            $balances[$id] ??= 0.0;
            $balances[$id] += $row->direction === 'debit' ? (float) $row->total : -(float) $row->total;
        }

        $entryLines = [];
        $incomeTotal = 0.0;
        $expenseTotal = 0.0;

        foreach ($balances as $accountId => $balance) {
            $account = $accounts[$accountId] ?? null;
            if (! $account) {
                continue;
            }

            if ($account->type === Account::TYPE_INCOME) {
                // 收入类贷方余额，结转时借记
                $amount = round(-$balance, 4);
                if ($amount > 0) {
                    $entryLines[] = ['account_id' => $account->id, 'direction' => 'debit', 'amount' => $amount, 'memo' => "结转 {$account->name}"];
                    $incomeTotal += $amount;
                } elseif ($amount < 0) {
                    $entryLines[] = ['account_id' => $account->id, 'direction' => 'credit', 'amount' => abs($amount), 'memo' => "结转 {$account->name}"];
                    $incomeTotal -= abs($amount);
                }
            } else {
                // 费用类借方余额，结转时贷记
                $amount = round($balance, 4);
                if ($amount > 0) {
                    $entryLines[] = ['account_id' => $account->id, 'direction' => 'credit', 'amount' => $amount, 'memo' => "结转 {$account->name}"];
                    $expenseTotal += $amount;
                } elseif ($amount < 0) {
                    $entryLines[] = ['account_id' => $account->id, 'direction' => 'debit', 'amount' => abs($amount), 'memo' => "结转 {$account->name}"];
                    $expenseTotal -= abs($amount);
                }
            }
        }

        if ($entryLines === []) {
            return null;
        }

        $net = round($incomeTotal - $expenseTotal, 4);
        if ($net > 0) {
            $entryLines[] = ['account_code' => '3002', 'direction' => 'credit', 'amount' => $net, 'memo' => '本期利润'];
        } elseif ($net < 0) {
            $entryLines[] = ['account_code' => '3002', 'direction' => 'debit', 'amount' => abs($net), 'memo' => '本期亏损'];
        }

        $journal = Journal::where('code', 'MISC')->firstOrFail();
        $entry = $this->entries->createEntry([
            'journal_id' => $journal->id,
            'entry_date' => $period->end_date->toDateString(),
            'source_type' => 'period_close',
            'source_id' => $period->id,
            'source_code' => $period->name,
            'description' => "损益结转 {$period->name}",
        ], $entryLines);

        return $this->entries->post($entry);
    }
}
