<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tax extends BaseModel
{
    protected $fillable = ['name', 'code', 'rate', 'type', 'account_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'account_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tax_id');
    }
}
