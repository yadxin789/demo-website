<?php

namespace App\Modules\Erp\Models\Bom;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Erp\Models\MasterData\Material;
use App\Modules\Erp\Models\Organization\User;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BomHeader extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'bom_code', 'product_material_id', 'version', 'status',
        'effective_date', 'remark', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class, 'bom_header_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
