<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnLine extends BaseModel
{
    protected $fillable = [
        'return_id', 'sales_order_line_id', 'pos_order_line_id', 'product_id',
        'location_id', 'lot_no', 'serial_no', 'qty', 'unit_price',
        'tax_amount', 'line_total', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'return_id' => 'integer',
            'sales_order_line_id' => 'integer',
            'pos_order_line_id' => 'integer',
            'product_id' => 'integer',
            'location_id' => 'integer',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    public function posOrderLine(): BelongsTo
    {
        return $this->belongsTo(PosOrderLine::class, 'pos_order_line_id');
    }
}
