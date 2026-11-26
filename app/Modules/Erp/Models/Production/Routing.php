<?php

namespace App\Modules\Erp\Models\Production;

use App\Modules\Erp\Models\MasterData\Material;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Routing extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['routing_code', 'product_material_id', 'version', 'status'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'routing_id')->orderBy('operation_seq');
    }
}
