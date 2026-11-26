<?php

namespace App\Modules\Erp\Models\Finance;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['tax_entity_id', 'tax_type', 'rate', 'status'];
    protected $casts = ['rate' => 'decimal:3', 'status' => 'boolean'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(TaxEntity::class, 'tax_entity_id');
    }
}
