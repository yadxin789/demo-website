<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrder extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'code', 'session_id', 'station_id', 'cashier_id', 'customer_id', 'order_date',
        'status', 'total_qty', 'subtotal', 'discount_amount', 'tax_amount', 'grand_total',
        'paid_amount', 'change_amount', 'note',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'station_id' => 'integer',
            'cashier_id' => 'integer',
            'customer_id' => 'integer',
            'order_date' => 'datetime',
            'total_qty' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'change_amount' => 'decimal:4',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
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

    public function lines(): HasMany
    {
        return $this->hasMany(PosOrderLine::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'order_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
