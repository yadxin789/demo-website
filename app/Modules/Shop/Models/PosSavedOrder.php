<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSavedOrder extends BaseModel
{
    protected $fillable = [
        'station_id', 'cashier_id', 'customer_id', 'cart_json', 'saved_at', 'expired_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'cashier_id' => 'integer',
            'customer_id' => 'integer',
            'cart_json' => 'array',
            'saved_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(PosStation::class, 'station_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    /**
     * 未过期（或未设过期时间）的挂单。
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('expired_at')
            ->orWhere('expired_at', '>=', now()));
    }
}
