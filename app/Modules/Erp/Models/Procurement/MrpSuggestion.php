<?php

namespace App\Modules\Erp\Models\Procurement;

use App\Modules\Erp\Models\MasterData\Material;
use App\Modules\Erp\Models\MasterData\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrpSuggestion extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'material_id', 'supplier_id', 'required_date', 'gross_qty', 'available_qty',
        'transit_qty', 'net_qty', 'status', 'demand_source', 'requisition_id', 'created_by',
    ];

    protected $casts = [
        'required_date' => 'date',
        'gross_qty' => 'decimal:4',
        'available_qty' => 'decimal:4',
        'transit_qty' => 'decimal:4',
        'net_qty' => 'decimal:2',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
