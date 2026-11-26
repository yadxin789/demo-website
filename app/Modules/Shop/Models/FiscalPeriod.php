<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FiscalPeriod extends BaseModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['name', 'start_date', 'end_date', 'status', 'closed_at'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
    }
}
