<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockLevel extends BaseModel
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'location_id', 'lot_no', 'serial_no',
        'qty', 'reserved_qty', 'avg_cost',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'warehouse_id' => 'integer',
            'location_id' => 'integer',
            'qty' => 'decimal:4',
            'reserved_qty' => 'decimal:4',
            'avg_cost' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** 可用量 = 现存量 - 预留量。 */
    public function availableQty(): float
    {
        return (float) $this->qty - (float) $this->reserved_qty;
    }

    /** 该序列号最近一条流水（用于台账追溯单据号）。 */
    public function latestMovement(): HasOne
    {
        return $this->hasOne(StockMovement::class, 'product_id', 'product_id')
            ->where('serial_no', $this->serial_no)
            ->latestOfMany('id');
    }
}
