<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends BaseModel
{
    protected $fillable = [
        'order_id', 'product_id', 'lot_no', 'serial_no', 'tax_id', 'qty', 'unit_price',
        'discount_rate', 'subtotal', 'tax_amount', 'line_note',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'tax_id' => 'integer',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_rate' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
