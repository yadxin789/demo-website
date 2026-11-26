<?php

namespace App\Modules\Shop\Services\Finance;

use App\Modules\Shop\Models\Account;
use App\Modules\Shop\Models\AccountEntry;
use App\Modules\Shop\Models\AccountEntryLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 税费归集与税表底表服务。
 * 销项税额取自已过账凭证 2004 科目的贷方发生额，进项税额取自 2003 科目的借方发生额，
 * 按税码分组汇总；税额与分录保持一致。另提供含税/不含税换算工具。
 */
class TaxReportService
{
    public const OUTPUT_ACCOUNT_CODE = '2004';
    public const INPUT_ACCOUNT_CODE = '2003';

    /**
     * @return array{
     *   outputLines: Collection, inputLines: Collection,
     *   outputTotal: float, inputTotal: float, payable: float
     * }
     */
    public function report(?Carbon $start, ?Carbon $end): array
    {
        $lines = AccountEntryLine::query()
            ->with('entry', 'tax', 'account')
            ->whereHas('entry', function ($q) use ($start, $end) {
                $q->where('status', AccountEntry::STATUS_POSTED)
                    ->when($start !== null, fn ($e) => $e->whereDate('entry_date', '>=', $start->toDateString()))
                    ->when($end !== null, fn ($e) => $e->whereDate('entry_date', '<=', $end->toDateString()));
            })
            ->whereHas('account', function ($q) {
                $q->whereIn('code', [self::OUTPUT_ACCOUNT_CODE, self::INPUT_ACCOUNT_CODE]);
            })
            ->get();

        $outputLines = $this->groupLines(
            $lines->filter(fn (AccountEntryLine $l) => $l->account?->code === self::OUTPUT_ACCOUNT_CODE
                && $l->direction === AccountEntryLine::DIRECTION_CREDIT)
        );

        $inputLines = $this->groupLines(
            $lines->filter(fn (AccountEntryLine $l) => $l->account?->code === self::INPUT_ACCOUNT_CODE
                && $l->direction === AccountEntryLine::DIRECTION_DEBIT)
        );

        $outputTotal = round($outputLines->sum('amount'), 4);
        $inputTotal = round($inputLines->sum('amount'), 4);
        $payable = round($outputTotal - $inputTotal, 4);

        return compact('outputLines', 'inputLines', 'outputTotal', 'inputTotal', 'payable');
    }

    /**
     * 按税码分组汇总税额。
     *
     * @param  Collection<int, AccountEntryLine>  $lines
     * @return Collection<int, array<string, mixed>>
     */
    private function groupLines(Collection $lines): Collection
    {
        return $lines
            ->groupBy(fn (AccountEntryLine $l) => $l->tax_id ?? 0)
            ->map(function (Collection $group, int $taxId) {
                $tax = $group->first()->tax;

                return [
                    'tax_id' => $taxId ?: null,
                    'name' => $tax?->name ?? '未指定税码',
                    'code' => $tax?->code ?? '—',
                    'rate' => $tax ? (float) $tax->rate : null,
                    'amount' => round($group->sum('amount'), 4),
                    'count' => $group->count(),
                ];
            })
            ->sortBy('code')
            ->values();
    }

    /** 不含税金额 → 含税金额。ratePercent 为百分比（如 13 表示 13%）。 */
    public static function grossFromNet(float $net, float $ratePercent): float
    {
        return round($net * (1 + $ratePercent / 100), 4);
    }

    /** 含税金额 → 不含税金额。 */
    public static function netFromGross(float $gross, float $ratePercent): float
    {
        $rate = 1 + $ratePercent / 100;

        return $rate > 0 ? round($gross / $rate, 4) : round($gross, 4);
    }

    /** 含税金额 → 税额。 */
    public static function taxFromGross(float $gross, float $ratePercent): float
    {
        return round($gross - self::netFromGross($gross, $ratePercent), 4);
    }
}
