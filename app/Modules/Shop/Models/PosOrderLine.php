<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderLine extends BaseModel
{
    protected $fillable = [
        'order_id', 'product_id', 'lot_no', 'serial_no', 'qty', 'unit_price', 'original_price',
        'discount_rate', 'discount_amount', 'tax_id', 'tax_amount', 'line_note',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'tax_id' => 'integer',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'original_price' => 'decimal:4',
            'discount_rate' => 'decimal:2',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }
}
