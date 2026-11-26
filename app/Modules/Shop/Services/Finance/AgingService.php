<?php

namespace App\Modules\Shop\Services\Finance;

use App\Modules\Shop\Models\Bill;
use App\Modules\Shop\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 应收应付账龄分析服务。
 * 以到期日（未填到期日回退单据日期）为基准计算逾期天数，分段：
 * 未到期 / 0–30 天 / 31–60 天 / 61–90 天 / 90 天以上；各段金额合计等于往来余额。
 */
class AgingService
{
    public const BUCKETS = ['not_due', '0_30', '31_60', '61_90', 'over_90'];

    public const BUCKET_LABELS = [
        'not_due' => '未到期',
        '0_30' => '0–30 天',
        '31_60' => '31–60 天',
        '61_90' => '61–90 天',
        'over_90' => '90 天以上',
    ];

    /** 应收账龄（客户）。 */
    public function receivables(?int $partnerId, ?Carbon $asOf): array
    {
        $invoices = Invoice::query()
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->with('partner')
            ->when($partnerId !== null, fn ($q) => $q->where('partner_id', $partnerId))
            ->get();

        return $this->aggregate($invoices, $asOf, 'invoice_date', 'partner');
    }

    /** 应付账龄（供应商）。 */
    public function payables(?int $partnerId, ?Carbon $asOf): array
    {
        $bills = Bill::query()
            ->where('status', '!=', Bill::STATUS_VOID)
            ->with('partner')
            ->when($partnerId !== null, fn ($q) => $q->where('partner_id', $partnerId))
            ->get();

        return $this->aggregate($bills, $asOf, 'bill_date', 'partner');
    }

    /**
     * 按往来单位聚合作龄分段。
     *
     * @param  Collection<int, Invoice|Bill>  $documents
     * @return array{rows: Collection, totals: array<string, float>, total: float}
     */
    private function aggregate(Collection $documents, ?Carbon $asOf, string $dateField, string $relation): array
    {
        $asOf ??= Carbon::today();

        $rows = $documents
            ->filter(fn ($doc) => $doc->balance() > 0)
            ->groupBy(fn ($doc) => $doc->partner_id ?? 0)
            ->map(function (Collection $docs) use ($asOf, $dateField, $relation) {
                $first = $docs->first();
                $buckets = array_fill_keys(self::BUCKETS, 0.0);
                $balance = 0.0;

                foreach ($docs as $doc) {
                    $amount = $doc->balance();
                    $dueDate = $doc->due_date ?: $doc->{$dateField};
                    $bucket = $this->bucket($asOf, $dueDate);
                    $buckets[$bucket] = round($buckets[$bucket] + $amount, 4);
                    $balance = round($balance + $amount, 4);
                }

                $partner = $first->{$relation};

                return [
                    'partner_id' => $first->partner_id,
                    'partner_code' => $partner?->code ?? '—',
                    'partner_name' => $partner?->name ?? '未关联往来单位',
                    'buckets' => $buckets,
                    'balance' => $balance,
                ];
            })
            ->sortBy('partner_name')
            ->values();

        $totals = array_fill_keys(self::BUCKETS, 0.0);
        foreach ($rows as $row) {
            foreach (self::BUCKETS as $bucket) {
                $totals[$bucket] = round($totals[$bucket] + $row['buckets'][$bucket], 4);
            }
        }

        $total = round(array_sum($totals), 4);

        return compact('rows', 'totals', 'total');
    }

    /** 依据逾期天数归入账龄段；$dueDate 晚于基准日为未到期。 */
    private function bucket(Carbon $asOf, ?Carbon $dueDate): string
    {
        if (! $dueDate) {
            return 'over_90';
        }

        // diffInDays 返回 目标日 − 基准日；逾期天数取反，到期日晚于基准日为负（未到期）。
        $days = -(int) $asOf->startOfDay()->diffInDays($dueDate->startOfDay(), false);

        if ($days < 0) {
            return 'not_due';
        }
        if ($days <= 30) {
            return '0_30';
        }
        if ($days <= 60) {
            return '31_60';
        }
        if ($days <= 90) {
            return '61_90';
        }

        return 'over_90';
    }
}
