<?php

namespace App\Modules\Shop\Services\Finance;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Models\Account;
use App\Modules\Shop\Models\AccountEntry;
use App\Modules\Shop\Models\AccountEntryLine;
use App\Modules\Shop\Models\AccountReconciliation;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\BankStatement;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 银行对账服务：将银行对账单流水与系统账（已过账凭证的银行科目分录行）逐笔勾对。
 * 一条流水与一条分录行一对一配对；勾对与解除均留审计日志。
 */
class ReconciliationService
{
    public function __construct(protected AuditLogger $logger)
    {
    }

    /** 系统账侧：已过账分录中指定银行科目的分录行，附带勾对状态。 */
    public function systemLines(?int $accountId, ?Carbon $start, ?Carbon $end, ?string $status): Collection
    {
        $lines = AccountEntryLine::query()
            ->with('entry', 'account')
            ->whereHas('entry', function ($q) {
                $q->where('status', AccountEntry::STATUS_POSTED);
            })
            ->when($accountId !== null, fn ($q) => $q->where('account_id', $accountId))
            ->when($start !== null, fn ($q) => $q->whereHas('entry', fn ($e) => $e->whereDate('entry_date', '>=', $start->toDateString())))
            ->when($end !== null, fn ($q) => $q->whereHas('entry', fn ($e) => $e->whereDate('entry_date', '<=', $end->toDateString())))
            ->get()
            ->sortBy(fn ($line) => [optional($line->entry?->entry_date)->toDateString(), $line->id])
            ->values();

        $matched = AccountReconciliation::whereIn('entry_line_id', $lines->pluck('id'))->get()
            ->keyBy('entry_line_id');

        $rows = $lines->map(function (AccountEntryLine $line) use ($matched) {
            $recon = $matched->get($line->id);

            return [
                'id' => $line->id,
                'entry_line_id' => $line->id,
                'account_id' => $line->account_id,
                'entry_code' => $line->entry?->code,
                'date' => optional($line->entry?->entry_date)->toDateString(),
                'direction' => $line->direction === AccountEntryLine::DIRECTION_DEBIT
                    ? BankStatement::DIRECTION_RECEIVE
                    : BankStatement::DIRECTION_PAY,
                'amount' => (float) $line->amount,
                'memo' => $line->memo ?: $line->entry?->description,
                'matched' => $recon !== null,
                'reconciliation_id' => $recon?->id,
                'reconciled_at' => $recon?->reconciled_at?->toDateTimeString(),
            ];
        })->values();

        if ($status === BankStatement::STATUS_MATCHED) {
            $rows = $rows->where('matched', true)->values();
        } elseif ($status === BankStatement::STATUS_UNMATCHED) {
            $rows = $rows->where('matched', false)->values();
        }

        return $rows;
    }

    /** 银行流水侧：对账单行，附带配对的系统分录行。 */
    public function statementLines(?int $accountId, ?Carbon $start, ?Carbon $end, ?string $status): Collection
    {
        $statements = BankStatement::query()
            ->with(['reconciliation.entryLine.entry', 'account'])
            ->when($accountId !== null, fn ($q) => $q->where('account_id', $accountId))
            ->when($start !== null, fn ($q) => $q->whereDate('statement_date', '>=', $start->toDateString()))
            ->when($end !== null, fn ($q) => $q->whereDate('statement_date', '<=', $end->toDateString()))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderBy('statement_date')
            ->orderBy('id')
            ->get();

        return $statements->map(function (BankStatement $statement) {
            $line = $statement->reconciliation?->entryLine;

            return [
                'id' => $statement->id,
                'account_id' => $statement->account_id,
                'date' => optional($statement->statement_date)->toDateString(),
                'direction' => $statement->direction,
                'amount' => (float) $statement->amount,
                'counterparty' => $statement->counterparty,
                'reference' => $statement->reference,
                'memo' => $statement->memo,
                'status' => $statement->status,
                'matched' => $statement->status === BankStatement::STATUS_MATCHED,
                'reconciliation_id' => $statement->reconciliation?->id,
                'entry_code' => $line?->entry?->code,
                'entry_line_id' => $line?->id,
                'matched_amount' => $line ? (float) $line->amount : null,
            ];
        })->values();
    }

    /** 录入一条银行对账单流水。 */
    public function addStatement(array $data, ?int $operatorId): BankStatement
    {
        $statement = BankStatement::create([
            'account_id' => $data['account_id'],
            'statement_date' => $data['statement_date'],
            'direction' => $data['direction'],
            'amount' => $data['amount'],
            'counterparty' => $data['counterparty'] ?? null,
            'reference' => $data['reference'] ?? null,
            'memo' => $data['memo'] ?? null,
            'note' => $data['note'] ?? null,
            'operator_id' => $operatorId,
            'status' => BankStatement::STATUS_UNMATCHED,
        ]);

        $this->logger->record([
            'module' => 'finance',
            'action' => AuditLog::ACTION_CREATE,
            'model' => BankStatement::class,
            'record_id' => $statement->id,
            'document_no' => $statement->reference,
            'message' => "登记银行流水 {$statement->direction} {$statement->amount}",
            'context' => ['account_id' => $statement->account_id],
        ]);

        return $statement;
    }

    /** 勾对一条银行流水与一条系统分录行。 */
    public function match(int $statementId, int $entryLineId, ?int $operatorId): AccountReconciliation
    {
        return DB::transaction(function () use ($statementId, $entryLineId, $operatorId) {
            $statement = BankStatement::lockForUpdate()->find($statementId);
            $line = AccountEntryLine::lockForUpdate()->with('entry')->find($entryLineId);

            if (! $statement || ! $line) {
                throw new AccountingException('勾对对象不存在');
            }

            if ($statement->status === BankStatement::STATUS_MATCHED) {
                throw new AccountingException('该银行流水已勾对');
            }

            if (AccountReconciliation::where('entry_line_id', $line->id)->exists()) {
                throw new AccountingException('该分录行已被勾对');
            }

            if ((int) $statement->account_id !== (int) $line->account_id) {
                throw new AccountingException('科目不一致，不能勾对');
            }

            $lineDirection = $line->direction === AccountEntryLine::DIRECTION_DEBIT
                ? BankStatement::DIRECTION_RECEIVE
                : BankStatement::DIRECTION_PAY;

            if ($lineDirection !== $statement->direction) {
                throw new AccountingException('收付方向不一致，不能勾对');
            }

            if (abs((float) $statement->amount - (float) $line->amount) > 0.001) {
                throw new AccountingException('金额不一致，不能勾对');
            }

            $entryCode = $line->entry?->code;
            $ref = $statement->reference ?: (string) $statement->id;

            $reconciliation = AccountReconciliation::create([
                'statement_id' => $statement->id,
                'entry_line_id' => $line->id,
                'account_id' => $statement->account_id,
                'amount' => (float) $line->amount,
                'operator_id' => $operatorId,
                'reconciled_at' => Carbon::now(),
            ]);

            $statement->status = BankStatement::STATUS_MATCHED;
            $statement->save();

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_UPDATE,
                'model' => AccountReconciliation::class,
                'record_id' => $reconciliation->id,
                'document_no' => $entryCode,
                'message' => "银行流水 {$ref} 与分录 {$entryCode} 勾对",
                'context' => [
                    'statement_id' => $statement->id,
                    'entry_line_id' => $line->id,
                    'amount' => (float) $line->amount,
                ],
            ]);

            return $reconciliation;
        });
    }

    /** 解除一对勾对。 */
    public function unmatch(int $reconciliationId, ?int $operatorId): void
    {
        DB::transaction(function () use ($reconciliationId, $operatorId) {
            $reconciliation = AccountReconciliation::lockForUpdate()->find($reconciliationId);

            if (! $reconciliation) {
                throw new AccountingException('勾对记录不存在');
            }

            $statement = BankStatement::find($reconciliation->statement_id);

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_UPDATE,
                'model' => AccountReconciliation::class,
                'record_id' => $reconciliation->id,
                'message' => "解除银行对账勾对 #{$reconciliation->id}",
                'context' => [
                    'statement_id' => $reconciliation->statement_id,
                    'entry_line_id' => $reconciliation->entry_line_id,
                    'amount' => (float) $reconciliation->amount,
                ],
            ]);

            $reconciliation->delete();

            if ($statement) {
                $statement->status = BankStatement::STATUS_UNMATCHED;
                $statement->save();
            }
        });
    }

    /**
     * 导入银行流水 CSV：支持常见中英文表头，自动识别 UTF-8/GBK，按关键字段去重。
     *
     * @return array{imported: int, duplicated: int, invalid: array<int, string>, total: int}
     */
    public function importCsv(string $path, int $accountId, ?int $operatorId): array
    {
        if (! is_file($path)) {
            throw new AccountingException("银行流水文件不存在：{$path}");
        }

        if (! Account::whereKey($accountId)->exists()) {
            throw new AccountingException("银行科目 #{$accountId} 不存在");
        }

        $size = (int) filesize($path);
        if ($size <= 0 || $size > 5242880) {
            throw new AccountingException('银行流水 CSV 必须大于 0 且不超过 5 MB');
        }

        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'GB18030');
            if ($converted === false) {
                throw new AccountingException('银行流水文件编码无法识别，请另存为 UTF-8 或 GBK');
            }
            $raw = $converted;
        }
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $header = null;
        $rows = [];
        $record = 0;

        while (($cells = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $record++;
            $cells = array_map(fn ($cell) => is_string($cell) ? trim($cell) : '', $cells);
            if (implode('', $cells) === '') {
                continue;
            }
            if ($header === null) {
                $header = $cells;
                continue;
            }
            $rows[] = ['line' => $record, 'cells' => $cells];
            if (count($rows) > 5000) {
                throw new AccountingException('单次最多导入 5000 行银行流水，请拆分后重试');
            }
        }
        fclose($handle);

        if ($header === null || $rows === []) {
            throw new AccountingException('银行流水 CSV 没有表头或数据行');
        }

        $map = $this->mapHeader($header);
        if ($map['date'] === null || ($map['amount'] === null && $map['income'] === null && $map['expense'] === null)) {
            throw new AccountingException('银行流水 CSV 至少需要「日期」和「金额」（或收入/支出金额）列');
        }

        return DB::transaction(fn () => $this->importStatementRows($accountId, $rows, $map, $operatorId));
    }

    /**
     * 未达账项匹配建议：同科目、同方向、金额一致、日期在容差窗口内，按匹配度排序。
     * 只给建议，不自动勾对。
     *
     * @return array{statements: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function suggestMatches(?int $accountId, ?Carbon $start, ?Carbon $end): array
    {
        $days = max(0, (int) config('ai.automation.bank_match_days', 7));
        $statements = $this->statementLines($accountId, $start, $end, BankStatement::STATUS_UNMATCHED);
        $systemLines = $this->systemLines($accountId, $start, $end, BankStatement::STATUS_UNMATCHED);
        $byDirection = $systemLines->groupBy('direction');

        $suggestions = [];

        foreach ($statements as $statement) {
            $candidates = [];
            foreach ($byDirection->get($statement['direction'], collect()) as $line) {
                if ((int) $statement['account_id'] !== (int) $line['account_id']) {
                    continue;
                }

                if (abs((float) $statement['amount'] - (float) $line['amount']) > 0.01) {
                    continue;
                }

                $dayDiff = abs($statement['date'] && $line['date']
                    ? Carbon::parse($statement['date'])->diffInDays(Carbon::parse($line['date']))
                    : 999);

                if ($dayDiff > $days) {
                    continue;
                }

                $score = 100;
                $score += match (true) {
                    $dayDiff === 0 => 30,
                    $dayDiff === 1 => 20,
                    $dayDiff <= 3 => 10,
                    default => 5,
                };

                $haystack = mb_strtolower(trim(($line['memo'] ?? '').' '.($line['entry_code'] ?? '')));
                $reference = mb_strtolower(trim((string) ($statement['reference'] ?? '')));
                if ($reference !== '' && str_contains($haystack, $reference)) {
                    $score += 20;
                }
                $counterparty = mb_strtolower(trim((string) ($statement['counterparty'] ?? '')));
                if ($counterparty !== '' && str_contains($haystack, $counterparty)) {
                    $score += 10;
                }

                $candidates[] = [
                    'entry_line_id' => $line['entry_line_id'],
                    'entry_code' => $line['entry_code'],
                    'date' => $line['date'],
                    'amount' => $line['amount'],
                    'memo' => $line['memo'],
                    'day_diff' => $dayDiff,
                    'score' => $score,
                ];
            }

            usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

            $best = $candidates[0] ?? null;
            $ambiguous = count($candidates) > 1 && $candidates[1]['score'] === $best['score'];

            $suggestions[] = [
                'statement_id' => $statement['id'],
                'date' => $statement['date'],
                'direction' => $statement['direction'],
                'amount' => $statement['amount'],
                'counterparty' => $statement['counterparty'],
                'reference' => $statement['reference'],
                'best' => $best,
                'candidates' => array_slice($candidates, 0, 3),
                'ambiguous' => $ambiguous,
                'auto_matchable' => $best !== null && ! $ambiguous,
            ];
        }

        return [
            'statements' => $suggestions,
            'summary' => [
                'bank_open' => $statements->count(),
                'system_open' => $systemLines->count(),
                'suggested' => count(array_filter($suggestions, fn ($row) => $row['best'] !== null)),
                'auto_matchable' => count(array_filter($suggestions, fn ($row) => $row['auto_matchable'])),
                'unmatched' => count(array_filter($suggestions, fn ($row) => $row['best'] === null)),
            ],
        ];
    }

    /**
     * @param  array<int, array{line:int,cells:array<int,string>}>  $rows
     * @param  array<string, int|null>  $map
     * @return array{imported: int, duplicated: int, invalid: array<int, string>, total: int}
     */
    private function importStatementRows(int $accountId, array $rows, array $map, ?int $operatorId): array
    {
        $imported = 0;
        $duplicated = 0;
        $invalid = [];

        foreach ($rows as $row) {
            $cells = $row['cells'];
            $dateRaw = $this->cell($cells, $map['date']);
            $amountRaw = $this->cell($cells, $map['amount']);
            $incomeRaw = $this->cell($cells, $map['income']);
            $expenseRaw = $this->cell($cells, $map['expense']);
            $directionRaw = $this->cell($cells, $map['direction']);

            try {
                $date = Carbon::parse($dateRaw);
            } catch (\Throwable) {
                $invalid[] = "第 {$row['line']} 行：日期无法识别";
                continue;
            }

            $direction = $this->normalizeDirection($directionRaw);
            $amountText = $amountRaw !== '' ? $amountRaw : ($incomeRaw !== '' ? $incomeRaw : $expenseRaw);
            $amount = $this->parseAmount($amountText);

            if ($amount === 0.0) {
                $invalid[] = "第 {$row['line']} 行：金额为空或为 0";
                continue;
            }

            if ($direction === null) {
                $direction = $incomeRaw !== '' && $expenseRaw === ''
                    ? BankStatement::DIRECTION_RECEIVE
                    : ($expenseRaw !== '' && $incomeRaw === ''
                        ? BankStatement::DIRECTION_PAY
                        : ($amount < 0 ? BankStatement::DIRECTION_PAY : BankStatement::DIRECTION_RECEIVE));
            }

            $amount = abs($amount);
            $reference = $this->cell($cells, $map['reference']) ?: null;
            $counterparty = $this->cell($cells, $map['counterparty']) ?: null;
            $memo = $this->cell($cells, $map['memo']) ?: null;

            $duplicate = BankStatement::query()
                ->where('account_id', $accountId)
                ->whereDate('statement_date', $date->toDateString())
                ->where('direction', $direction)
                ->where('amount', $amount)
                ->when($reference !== null, fn ($q) => $q->where('reference', $reference))
                ->when($reference === null, fn ($q) => $q->where('counterparty', $counterparty)->where('memo', $memo))
                ->exists();

            if ($duplicate) {
                $duplicated++;
                continue;
            }

            $this->addStatement([
                'account_id' => $accountId,
                'statement_date' => $date->toDateString(),
                'direction' => $direction,
                'amount' => $amount,
                'counterparty' => $counterparty,
                'reference' => $reference,
                'memo' => $memo,
            ], $operatorId);

            $imported++;
        }

        return [
            'imported' => $imported,
            'duplicated' => $duplicated,
            'invalid' => $invalid,
            'total' => count($rows),
        ];
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int|null>
     */
    private function mapHeader(array $header): array
    {
        $normalized = [];
        foreach ($header as $index => $name) {
            $normalized[$this->normalizeHeader($name)] = $index;
        }

        $aliases = [
            'date' => ['日期', '交易日期', '记账日期', '发生日期', 'date', 'statementdate', 'transactiondate'],
            'direction' => ['方向', '借贷', '收支', '收付', 'direction', 'drcr'],
            'amount' => ['金额', '发生额', '交易金额', 'amount', 'transactionamount'],
            'income' => ['收入', '收入金额', '贷方金额', '存入金额', 'credit', 'income'],
            'expense' => ['支出', '支出金额', '借方金额', '支取金额', 'debit', 'expense'],
            'counterparty' => ['对方', '对方户名', '交易对手', '对方名称', 'counterparty', 'payee'],
            'reference' => ['回单号', '交易流水号', '流水号', '参考号', 'reference', 'serialno'],
            'memo' => ['摘要', '备注', '用途', '附言', 'memo', 'remark', 'description'],
        ];

        $map = [];
        foreach ($aliases as $key => $names) {
            $map[$key] = null;
            foreach ($names as $name) {
                if (array_key_exists($this->normalizeHeader($name), $normalized)) {
                    $map[$key] = $normalized[$this->normalizeHeader($name)];
                    break;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $name): string
    {
        return mb_strtolower(preg_replace('/[\s\-_\/（）()]+/u', '', trim($name)) ?? '');
    }

    /** @param array<int, string> $cells */
    private function cell(array $cells, ?int $index): string
    {
        return $index === null ? '' : trim((string) ($cells[$index] ?? ''));
    }

    private function normalizeDirection(string $value): ?string
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return null;
        }
        if (preg_match('/收|入账|贷方|存入|进账|credit|receive/u', $value)) {
            return BankStatement::DIRECTION_RECEIVE;
        }
        if (preg_match('/付|出账|借方|支取|支出|debit|pay/u', $value)) {
            return BankStatement::DIRECTION_PAY;
        }

        return null;
    }

    private function parseAmount(string $value): float
    {
        $value = str_replace([',', '，', '¥', '￥', ' '], '', trim($value));
        if ($value === '') {
            return 0.0;
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
