<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends BaseModel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'product_id', 'lot_no', 'supplier_id', 'receipt_date', 'expiry_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'supplier_id' => 'integer',
            'receipt_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'product_id', 'product_id')
            ->where('lot_no', $this->lot_no);
    }
}
