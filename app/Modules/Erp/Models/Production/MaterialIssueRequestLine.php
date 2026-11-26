<?php

namespace App\Modules\Erp\Models\Production;

use App\Modules\Erp\Models\MasterData\Material;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialIssueRequestLine extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;

    protected $fillable = ['request_id', 'material_id', 'qty', 'standard_qty', 'is_over_bom'];

    protected $casts = [
        'qty' => 'decimal:2',
        'standard_qty' => 'decimal:2',
        'is_over_bom' => 'boolean',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaterialIssueRequest::class, 'request_id');
    }
}
