<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 不可变库存流水：只增不改不删。
 */
class StockMovement extends BaseModel
{
    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';
    public const DIRECTION_TRANSFER = 'transfer';
    public const DIRECTION_ADJUST = 'adjust';

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'warehouse_id', 'location_id', 'lot_no', 'serial_no',
        'direction', 'qty_before', 'qty_after', 'qty', 'unit_cost',
        'movement_date', 'source_type', 'source_id', 'source_code',
        'operator_id', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'warehouse_id' => 'integer',
            'location_id' => 'integer',
            'qty_before' => 'decimal:4',
            'qty_after' => 'decimal:4',
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'movement_date' => 'datetime',
            'source_id' => 'integer',
            'operator_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }
}
