<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends BaseModel
{
    protected $fillable = ['name', 'code', 'precision', 'is_base'];

    protected function casts(): array
    {
        return [
            'precision' => 'integer',
            'is_base' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }
}
