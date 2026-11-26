<?php

namespace App\Modules\Erp\Models\MasterData;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'category_code', 'category_name', 'description', 'sort_order', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort_order' => 'integer',
    ];

    public function subcategories(): HasMany
    {
        return $this->hasMany(MaterialSubcategory::class, 'category_code', 'category_code');
    }
}
