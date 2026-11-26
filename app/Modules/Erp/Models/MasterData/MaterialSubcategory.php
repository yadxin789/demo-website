<?php

namespace App\Modules\Erp\Models\MasterData;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MaterialSubcategory extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'category_code', 'subcategory_code', 'subcategory_name', 'description',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_code', 'category_code');
    }
}
