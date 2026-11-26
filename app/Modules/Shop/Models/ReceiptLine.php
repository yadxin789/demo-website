<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptLine extends BaseModel
{
    protected $fillable = [
        'receipt_id', 'purchase_order_line_id', 'product_id', 'location_id',
        'lot_no', 'serial_no', 'qty', 'unit_cost', 'line_note',
    ];

    protected function casts(): array
    {
        return [
            'receipt_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'product_id' => 'integer',
            'location_id' => 'integer',
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
