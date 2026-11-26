<?php

namespace App\Modules\Erp\Models\Finance;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxEntity extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'type', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'tax_entity_id');
    }
}
