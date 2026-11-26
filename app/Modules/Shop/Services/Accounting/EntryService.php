<?php

namespace App\Modules\Shop\Services\Accounting;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Models\Account;
use App\Modules\Shop\Models\AccountEntry;
use App\Modules\Shop\Models\AccountEntryLine;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\FiscalPeriod;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 凭证分录服务：生成草稿凭证、借贷平衡校验、过账锁定与审计留痕。
 * 分录按规划 11.1 科目规则由各业务场景调用本服务生成。
 */
class EntryService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected AuditLogger $logger
    ) {
    }

    /**
     * 创建草稿凭证并校验借贷平衡；不平衡则回滚。
     *
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines  [{account_code|account_id, direction, amount, memo?, tax_id?, partner_id?}]
     */
    public function createEntry(array $header, array $lines): AccountEntry
    {
        if ($lines === []) {
            throw new AccountingException('凭证至少需要一行分录');
        }

        return DB::transaction(function () use ($header, $lines) {
            $entry = AccountEntry::create([
                'code' => $this->sequences->generate('voucher'),
                'journal_id' => (int) $header['journal_id'],
                'entry_date' => isset($header['entry_date']) ? Carbon::parse($header['entry_date']) : Carbon::today(),
                'status' => AccountEntry::STATUS_DRAFT,
                'source_type' => $header['source_type'] ?? null,
                'source_id' => $header['source_id'] ?? null,
                'source_code' => $header['source_code'] ?? null,
                'description' => $header['description'] ?? null,
                'creator_id' => $header['creator_id'] ?? null,
            ]);

            $sort = 0;
            foreach ($lines as $line) {
                $accountId = isset($line['account_id'])
                    ? (int) $line['account_id']
                    : $this->accountByCode((string) ($line['account_code'] ?? ''))->id;

                $direction = $line['direction'] ?? '';
                if (! in_array($direction, [AccountEntryLine::DIRECTION_DEBIT, AccountEntryLine::DIRECTION_CREDIT], true)) {
                    throw new AccountingException('分录方向必须为借或贷');
                }

                $amount = (float) ($line['amount'] ?? 0);
                if ($amount <= 0) {
                    throw new AccountingException('分录金额必须大于 0');
                }

                $entry->lines()->create([
                    'account_id' => $accountId,
                    'direction' => $direction,
                    'amount' => $amount,
                    'tax_id' => $line['tax_id'] ?? null,
                    'partner_id' => $line['partner_id'] ?? null,
                    'memo' => $line['memo'] ?? null,
                    'sort' => $sort++,
                ]);
            }

            $entry->load('lines');

            if (! $entry->isBalanced()) {
                throw new AccountingException(sprintf(
                    '凭证借贷不平衡：借方 %.4f，贷方 %.4f，差额 %.4f',
                    $entry->totalDebit(),
                    $entry->totalCredit(),
                    abs($entry->totalDebit() - $entry->totalCredit())
                ));
            }

            return $entry;
        });
    }

    /**
     * 过账：平衡校验通过后锁定分录；已过账/已冲销不可重复过账。
     */
    public function post(AccountEntry $entry): AccountEntry
    {
        if ($entry->isLocked()) {
            throw new AccountingException("凭证 {$entry->code} 已锁定，不能重复过账");
        }

        if (! $entry->isBalanced()) {
            throw new AccountingException("凭证 {$entry->code} 借贷不平衡，不能过账");
        }

        if ($entry->entry_date) {
            $period = FiscalPeriod::activeOn($entry->entry_date->toDateString())->first();
            if ($period && $period->isClosed()) {
                throw new AccountingException("会计期间 {$period->name} 已关闭，不能过账");
            }
        }

        $entry->status = AccountEntry::STATUS_POSTED;
        $entry->posted_at = Carbon::now();
        $entry->save();

        $this->logger->record([
            'module' => 'finance',
            'action' => AuditLog::ACTION_APPROVE,
            'model' => AccountEntry::class,
            'record_id' => $entry->id,
            'document_no' => $entry->code,
            'message' => "凭证 {$entry->code} 过账",
            'context' => [
                'journal_id' => $entry->journal_id,
                'debit' => $entry->totalDebit(),
                'credit' => $entry->totalCredit(),
            ],
        ]);

        return $entry;
    }

    /**
     * 冲销已过账凭证：生成方向相反的红字反向凭证并过账，原凭证置为已冲销。
     */
    public function reverse(AccountEntry $entry): AccountEntry
    {
        if ($entry->status !== AccountEntry::STATUS_POSTED) {
            throw new AccountingException("凭证 {$entry->code} 非已过账状态，不能冲销");
        }

        return DB::transaction(function () use ($entry) {
            $entry->load('lines');

            $lines = $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'direction' => $line->direction === AccountEntryLine::DIRECTION_DEBIT
                    ? AccountEntryLine::DIRECTION_CREDIT
                    : AccountEntryLine::DIRECTION_DEBIT,
                'amount' => (float) $line->amount,
                'tax_id' => $line->tax_id,
                'partner_id' => $line->partner_id,
                'memo' => '冲销 ' . $entry->code . (isset($line->memo) ? "：{$line->memo}" : ''),
            ])->all();

            $reversal = $this->createEntry([
                'journal_id' => $entry->journal_id,
                'entry_date' => $entry->entry_date?->toDateString(),
                'source_type' => 'entry_reversal',
                'source_id' => $entry->id,
                'source_code' => $entry->code,
                'description' => "冲销凭证 {$entry->code}",
            ], $lines);

            $this->post($reversal);

            $entry->status = AccountEntry::STATUS_REVERSED;
            $entry->save();

            $this->logger->record([
                'module' => 'finance',
                'action' => AuditLog::ACTION_UPDATE,
                'model' => AccountEntry::class,
                'record_id' => $entry->id,
                'document_no' => $entry->code,
                'message' => "凭证 {$entry->code} 已被 {$reversal->code} 冲销",
                'context' => [
                    'reversal_entry_id' => $reversal->id,
                    'reversal_code' => $reversal->code,
                ],
            ]);

            return $reversal;
        });
    }

    private function accountByCode(string $code): Account
    {
        $account = Account::where('code', $code)->first();

        if (! $account) {
            throw new AccountingException("科目编码 {$code} 不存在");
        }

        return $account;
    }
}
