<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends BaseModel
{
    public const SOURCE_SALES_ORDER = 'sales_order';
    public const SOURCE_POS_ORDER = 'pos_order';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public const REFUND_CREDIT = 'credit';
    public const REFUND_CASH = 'cash';
    public const REFUND_BANK = 'bank';

    protected $fillable = [
        'code', 'source_type', 'sales_order_id', 'pos_order_id', 'customer_id',
        'warehouse_id', 'return_date', 'status', 'reason', 'refund_method',
        'refund_amount', 'operator_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'sales_order_id' => 'integer',
            'pos_order_id' => 'integer',
            'customer_id' => 'integer',
            'warehouse_id' => 'integer',
            'operator_id' => 'integer',
            'return_date' => 'date',
            'refund_amount' => 'decimal:4',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class, 'return_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
