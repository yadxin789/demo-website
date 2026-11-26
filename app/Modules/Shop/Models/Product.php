<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends BaseModel
{
    protected $fillable = [
        'code', 'barcode', 'name', 'category_id', 'unit_id', 'tax_id',
        'cost_method', 'standard_cost', 'avg_cost', 'list_price',
        'traceability', 'shelf_life_days', 'description', 'is_active',
        'image_path', 'image_thumb_path', 'image_mime', 'image_size',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'unit_id' => 'integer',
            'tax_id' => 'integer',
            'standard_cost' => 'decimal:4',
            'avg_cost' => 'decimal:4',
            'list_price' => 'decimal:4',
            'shelf_life_days' => 'integer',
            'is_active' => 'boolean',
            'image_size' => 'integer',
        ];
    }

    /**
     * 是否已有主图。
     */
    public function hasImage(): bool
    {
        return (bool) $this->image_path;
    }

    /**
     * 主图访问地址（走应用路由，thumb 缩略图 / full 原图）。
     */
    public function imageUrl(string $variant = 'thumb'): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return route('shop.products.image', ['product' => $this->getKey(), 'variant' => $variant]);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
