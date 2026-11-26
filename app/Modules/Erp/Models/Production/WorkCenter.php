<?php

namespace App\Modules\Erp\Models\Production;

use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCenter extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'location', 'capacity_per_day', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'work_center_id');
    }
}
