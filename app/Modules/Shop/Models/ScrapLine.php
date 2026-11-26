<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapLine extends BaseModel
{
    protected $fillable = [
        'scrap_id', 'product_id', 'location_id', 'lot_no', 'serial_no',
        'qty', 'unit_cost', 'loss_amount', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'scrap_id' => 'integer',
            'product_id' => 'integer',
            'location_id' => 'integer',
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'loss_amount' => 'decimal:4',
        ];
    }

    public function scrap(): BelongsTo
    {
        return $this->belongsTo(Scrap::class, 'scrap_id');
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
