<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnLine extends BaseModel
{
    protected $fillable = [
        'return_id', 'receipt_line_id', 'purchase_order_line_id', 'product_id',
        'location_id', 'lot_no', 'serial_no', 'qty', 'unit_cost', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'return_id' => 'integer',
            'receipt_line_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'product_id' => 'integer',
            'location_id' => 'integer',
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(ReceiptLine::class, 'receipt_line_id');
    }
}
