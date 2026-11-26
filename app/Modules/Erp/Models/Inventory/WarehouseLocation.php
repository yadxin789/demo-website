<?php

namespace App\Modules\Erp\Models\Inventory;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WarehouseLocation extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'warehouse_id', 'location_code', 'zone', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
