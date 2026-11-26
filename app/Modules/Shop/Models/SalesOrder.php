<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    /** 合法状态流转：当前状态 => 可转入状态列表。 */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_RETURNED],
        self::STATUS_RETURNED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'code', 'customer_id', 'warehouse_id', 'order_date', 'expected_date', 'status',
        'total_qty', 'total_amount', 'discount_amount', 'tax_amount', 'grand_total',
        'note', 'creator_id', 'approver_id', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'warehouse_id' => 'integer',
            'creator_id' => 'integer',
            'approver_id' => 'integer',
            'order_date' => 'date',
            'expected_date' => 'date',
            'approved_at' => 'datetime',
            'total_qty' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
