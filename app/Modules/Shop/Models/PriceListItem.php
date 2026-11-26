<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends BaseModel
{
    protected $fillable = ['price_list_id', 'product_id', 'min_qty', 'price', 'valid_from', 'valid_to'];

    protected function casts(): array
    {
        return [
            'price_list_id' => 'integer',
            'product_id' => 'integer',
            'min_qty' => 'decimal:4',
            'price' => 'decimal:4',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 该阶梯价在指定日期、数量下是否生效。
     */
    public function isEffective(float $qty, ?string $date = null): bool
    {
        $date = $date ? \Illuminate\Support\Carbon::parse($date) : \Illuminate\Support\Carbon::today();

        if ($this->min_qty !== null && (float) $this->min_qty > $qty) {
            return false;
        }

        if ($this->valid_from && $date->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && $date->gt($this->valid_to)) {
            return false;
        }

        return true;
    }
}
