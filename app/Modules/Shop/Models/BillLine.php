<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillLine extends BaseModel
{
    protected $fillable = [
        'bill_id', 'purchase_order_line_id', 'product_id', 'qty',
        'unit_price', 'amount', 'tax_amount', 'line_note',
    ];

    protected function casts(): array
    {
        return [
            'bill_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'product_id' => 'integer',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }
}
