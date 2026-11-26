<?php

namespace App\Modules\Shop\Support;

use App\Modules\Shop\Models\Sequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 单据编号生成器。
 *
 * - 事务内 `lockForUpdate` 行锁，并发取号不重复；
 * - 支持按 月 / 年 重置流水；
 * - 编号 = 前缀 + 左补零流水 + 后缀。
 */
class SequenceGenerator
{
    public function generate(string $code): string
    {
        return DB::transaction(function () use ($code) {
            $seq = Sequence::where('code', $code)->lockForUpdate()->first();

            if (! $seq) {
                throw new RuntimeException("未配置单据编号规则：{$code}");
            }

            $this->maybeReset($seq);

            $number = $seq->next_number;
            $seq->next_number = $number + 1;
            $seq->save();

            return $this->format($seq, $number);
        });
    }

    /**
     * 预览下一个编号（不消耗流水）。
     */
    public function preview(Sequence $seq): string
    {
        return $this->format($seq, $seq->next_number);
    }

    private function format(Sequence $seq, int $number): string
    {
        return $seq->prefix
            . str_pad((string) $number, $seq->padding, '0', STR_PAD_LEFT)
            . $seq->suffix;
    }

    private function maybeReset(Sequence $seq): void
    {
        if ($seq->period_mode === Sequence::PERIOD_NONE) {
            return;
        }

        $now = Carbon::today();
        $last = $seq->last_reset_at;

        if (! $last) {
            $seq->last_reset_at = $now;
            $seq->next_number = 1;

            return;
        }

        $needReset = match ($seq->period_mode) {
            Sequence::PERIOD_DAY => ! $last->isSameDay($now),
            Sequence::PERIOD_MONTH => ! $last->isSameMonth($now),
            Sequence::PERIOD_YEAR => ! $last->isSameYear($now),
            default => false,
        };

        if ($needReset) {
            $seq->last_reset_at = $now;
            $seq->next_number = 1;
        }
    }
}
