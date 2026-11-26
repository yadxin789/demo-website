<?php

namespace App\Modules\Shop\Services\Finance;

use App\Modules\Shop\Models\Account;
use App\Modules\Shop\Models\AccountEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 三张财务报表初稿：以已过账凭证为唯一数据源，按科目余额汇总。
 * 资产负债表为截至日期的累计余额；利润表为期间发生额；现金流量表按资金科目流水方向归集。
 * 页面明确标注初稿，需会计复核后方可作为对外报表。
 */
class FinancialReportService
{
    /** @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>, balanced: bool} */
    public function balanceSheet(?Carbon $asOf): array
    {
        $date = ($asOf ?? Carbon::today())->toDateString();
        $rows = [];
        $totals = ['asset' => 0.0, 'liability' => 0.0, 'equity' => 0.0];

        foreach ($this->balances(null, $date) as $account) {
            if (! in_array($account->type, [Account::TYPE_ASSET, Account::TYPE_LIABILITY, Account::TYPE_EQUITY], true)) {
                continue;
            }
            $amount = $this->presentAmount($account);
            if (abs($amount) < 0.00005) {
                continue;
            }
            $rows[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => $this->typeLabel($account->type),
                'amount' => $amount,
            ];
            $totals[$account->type] += $amount;
        }

        $totals = array_map(fn ($v) => round($v, 4), $totals);
        $right = round($totals['liability'] + $totals['equity'], 4);

        return [
            'rows' => $rows,
            'totals' => $totals,
            'balanced' => abs(round($totals['asset'] - $right, 4)) < 0.0001,
            'right_total' => $right,
            'as_of' => $date,
        ];
    }

    /** @return array{rows: array<int, array<string, mixed>>, income: float, expense: float, profit: float} */
    public function incomeStatement(?Carbon $start, ?Carbon $end): array
    {
        $endDate = ($end ?? Carbon::today())->toDateString();
        $startDate = ($start ?? Carbon::parse($endDate)->startOfMonth())->toDateString();

        $rows = [];
        $income = 0.0;
        $expense = 0.0;

        foreach ($this->balances($startDate, $endDate) as $account) {
            if (! in_array($account->type, [Account::TYPE_INCOME, Account::TYPE_EXPENSE], true)) {
                continue;
            }
            // 收入按贷方为正，费用按借方为正
            $amount = $account->type === Account::TYPE_INCOME
                ? round(-(float) $account->balance, 4)
                : round((float) $account->balance, 4);
            if (abs($amount) < 0.00005) {
                continue;
            }
            $rows[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => $account->type === Account::TYPE_INCOME ? '收入' : '费用',
                'amount' => $amount,
            ];
            if ($account->type === Account::TYPE_INCOME) {
                $income += $amount;
            } else {
                $expense += $amount;
            }
        }

        return [
            'rows' => $rows,
            'income' => round($income, 4),
            'expense' => round($expense, 4),
            'profit' => round($income - $expense, 4),
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * 现金流量表（直接法初稿）：按凭证中资金科目（1004 库存现金 / 1005 银行存款）的净流动，
     * 结合来源类型归类为经营、投资、筹资活动。
     *
     * @return array{rows: array<int, array<string, mixed>>, inflow: float, outflow: float, net: float}
     */
    public function cashFlow(?Carbon $start, ?Carbon $end): array
    {
        $endDate = ($end ?? Carbon::today())->toDateString();
        $startDate = ($start ?? Carbon::parse($endDate)->startOfMonth())->toDateString();

        $entryIds = DB::table('account_entry_lines')
            ->join('accounts', 'accounts.id', '=', 'account_entry_lines.account_id')
            ->whereIn('accounts.code', ['1004', '1005'])
            ->pluck('account_entry_lines.entry_id')
            ->unique()
            ->values();

        $entries = AccountEntry::query()
            ->whereIn('id', $entryIds)
            ->where('status', AccountEntry::STATUS_POSTED)
            ->whereDate('entry_date', '>=', $startDate)
            ->whereDate('entry_date', '<=', $endDate)
            ->with('lines.account')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $rows = [];
        $inflow = 0.0;
        $outflow = 0.0;

        foreach ($entries as $entry) {
            $cashNet = 0.0;
            foreach ($entry->lines as $line) {
                if (! in_array((string) $line->account?->code, ['1004', '1005'], true)) {
                    continue;
                }
                $cashNet += $line->direction === 'debit' ? (float) $line->amount : -(float) $line->amount;
            }
            $cashNet = round($cashNet, 4);
            if (abs($cashNet) < 0.00005) {
                continue;
            }

            $category = $this->cashCategory((string) $entry->source_type, $entry);
            $rows[] = [
                'date' => $entry->entry_date?->toDateString(),
                'code' => $entry->code,
                'description' => $entry->description,
                'category' => $category,
                'amount' => $cashNet,
            ];

            if ($cashNet > 0) {
                $inflow += $cashNet;
            } else {
                $outflow += abs($cashNet);
            }
        }

        return [
            'rows' => $rows,
            'inflow' => round($inflow, 4),
            'outflow' => round($outflow, 4),
            'net' => round($inflow - $outflow, 4),
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    private function cashCategory(string $sourceType, AccountEntry $entry): string
    {
        if (in_array($sourceType, ['sale', 'pos', 'invoice', 'payment_receive', 'receipt'], true)) {
            return '经营活动';
        }
        if (in_array($sourceType, ['purchase', 'bill', 'expense', 'expense_payment', 'scrap', 'stock_count'], true)) {
            return '经营活动';
        }
        if (in_array($sourceType, ['payment', 'payment_pay'], true)) {
            return '经营活动';
        }
        if (str_contains($sourceType, 'reversal')) {
            return '冲销调整';
        }

        // 兜底按对方科目类型判断：收入/费用为经营，固定资产类为投资，权益/长期负债为筹资
        $counterTypes = $entry->lines
            ->filter(fn ($line) => ! in_array((string) $line->account?->code, ['1004', '1005'], true))
            ->map(fn ($line) => (string) $line->account?->type)
            ->unique();

        if ($counterTypes->contains(Account::TYPE_INCOME) || $counterTypes->contains(Account::TYPE_EXPENSE)) {
            return '经营活动';
        }
        if ($counterTypes->contains(Account::TYPE_EQUITY)) {
            return '筹资活动';
        }

        return '经营活动';
    }

    /**
     * @return \Illuminate\Support\Collection<int, Account>
     */
    private function balances(?string $startDate, string $endDate): \Illuminate\Support\Collection
    {
        $query = DB::table('account_entry_lines')
            ->join('account_entries', 'account_entries.id', '=', 'account_entry_lines.entry_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'account_entry_lines.account_id')
            ->where('account_entries.status', AccountEntry::STATUS_POSTED)
            ->whereDate('account_entries.entry_date', '<=', $endDate)
            ->when($startDate !== null, fn ($q) => $q->whereDate('account_entries.entry_date', '>=', $startDate))
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.balance_side')
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, accounts.balance_side,
                SUM(CASE WHEN account_entry_lines.direction = ? THEN account_entry_lines.amount ELSE -account_entry_lines.amount END) as balance', ['debit'])
            ->get();

        return $query
            ->filter(fn ($row) => $row->id !== null)
            ->map(function ($row) {
                $account = new Account([
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'balance_side' => $row->balance_side,
                ]);
                $account->id = (int) $row->id;
                $account->balance = (float) $row->balance;

                return $account;
            })
            ->sortBy('code')
            ->values();
    }

    /** 资产按借方余额为正，负债/权益按贷方余额为正。 */
    private function presentAmount(Account $account): float
    {
        $balance = (float) ($account->balance ?? 0);

        return in_array($account->type, [Account::TYPE_LIABILITY, Account::TYPE_EQUITY], true)
            ? round(-$balance, 4)
            : round($balance, 4);
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            Account::TYPE_ASSET => '资产',
            Account::TYPE_LIABILITY => '负债',
            Account::TYPE_EQUITY => '所有者权益',
            default => $type,
        };
    }
}
