<?php

namespace App\Modules\Erp\Models\Sales;

use App\Modules\Erp\Models\MasterData\Material;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerReturnLine extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'return_id', 'material_id', 'lot_no', 'qty', 'disposition', 'disposition_qty', 'handled_at',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'disposition_qty' => 'decimal:2',
        'handled_at' => 'datetime',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
