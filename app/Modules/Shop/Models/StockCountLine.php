<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends BaseModel
{
    protected $fillable = [
        'count_id', 'product_id', 'location_id', 'lot_no', 'serial_no',
        'book_qty', 'counted_qty', 'variance_qty', 'unit_cost', 'variance_value', 'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'count_id' => 'integer',
            'product_id' => 'integer',
            'location_id' => 'integer',
            'book_qty' => 'decimal:4',
            'counted_qty' => 'decimal:4',
            'variance_qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'variance_value' => 'decimal:4',
            'adjusted_at' => 'datetime',
        ];
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'count_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isCounted(): bool
    {
        return $this->counted_qty !== null;
    }
}
