<?php

declare(strict_types=1);

namespace App\Modules\Mes\Support;

use Illuminate\Support\Carbon;

/**
 * 报表日期区间解析（M6-03 三张报表共用）。
 *
 * 规则：非法或缺失的 from/to 一律回落到默认区间，绝不抛 500；
 * from 晚于 to 时自动对调；两端都归一化到当天 00:00。
 */
final class DateRange
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolve(?string $from, ?string $to, int $daysBack = 13): array
    {
        $end = self::parse($to) ?? now();
        $start = self::parse($from) ?? $end->copy()->subDays($daysBack);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }

    private static function parse(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
