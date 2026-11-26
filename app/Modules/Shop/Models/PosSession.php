<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends BaseModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'station_id', 'cashier_id', 'opened_at', 'opened_balance', 'closed_at', 'status',
        'expected_cash', 'counted_cash', 'variance_amount', 'total_sales', 'total_refund',
        'total_discount', 'note',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'cashier_id' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'expected_cash' => 'decimal:4',
            'counted_cash' => 'decimal:4',
            'variance_amount' => 'decimal:4',
            'opened_balance' => 'decimal:4',
            'total_sales' => 'decimal:4',
            'total_refund' => 'decimal:4',
            'total_discount' => 'decimal:4',
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

    public function counts(): HasMany
    {
        return $this->hasMany(PosSessionCashCount::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
