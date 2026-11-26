<?php

namespace App\Modules\Erp\Models\Inventory;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'warehouse_code', 'name', 'type', 'address', 'status', 'department_id',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'warehouse_id');
    }
}
