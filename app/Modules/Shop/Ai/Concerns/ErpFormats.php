<?php

namespace App\Modules\Shop\Ai\Concerns;

use Illuminate\Support\Carbon;

trait ErpFormats
{
    protected function money(mixed $value): string
    {
        return '¥' . number_format((float) $value, 2);
    }

    protected function qty(mixed $value): string
    {
        $number = (float) $value;

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }

    protected function date(mixed $value): string
    {
        if (! $value) {
            return '—';
        }

        return $value instanceof Carbon
            ? $value->format('Y-m-d')
            : Carbon::parse($value)->format('Y-m-d');
    }

    protected function dateTime(mixed $value): string
    {
        if (! $value) {
            return '—';
        }

        return $value instanceof Carbon
            ? $value->format('Y-m-d H:i')
            : Carbon::parse($value)->format('Y-m-d H:i');
    }

    protected function salesStatus(string $status): string
    {
        return match ($status) {
            'draft' => '草稿',
            'confirmed' => '已确认',
            'shipped' => '已出库',
            'returned' => '已退货',
            'cancelled' => '已取消',
            default => $status,
        };
    }

    protected function purchaseStatus(string $status): string
    {
        return match ($status) {
            'draft' => '草稿',
            'confirmed' => '已确认',
            'partial' => '部分收货',
            'received' => '已收货',
            'closed' => '已关闭',
            'cancelled' => '已取消',
            default => $status,
        };
    }

    protected function posStatus(string $status): string
    {
        return match ($status) {
            'draft' => '挂单',
            'completed' => '已完成',
            'refunded' => '已退款',
            default => $status,
        };
    }

    protected function limit(mixed $value, int $default = 10, int $max = 50): int
    {
        return max(1, min($max, (int) ($value ?: $default)));
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (! $value || ! is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
