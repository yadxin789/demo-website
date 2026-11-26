<?php

namespace App\Modules\Erp\Models\Inventory;

use App\Modules\Erp\Models\MasterData\Material;
use App\Modules\Erp\Models\Organization\User;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InventoryTransaction extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'material_id', 'lot_no', 'warehouse_id', 'location_id', 'txn_type',
        'qty_change', 'qty_before', 'qty_after', 'from_status', 'to_status',
        'std_cost', 'actual_cost', 'price_variance',
        'ref_type', 'ref_id', 'operator_id', 'remark', 'created_at',
    ];

    protected $casts = [
        'qty_change' => 'decimal:2',
        'qty_before' => 'decimal:2',
        'qty_after' => 'decimal:2',
        'std_cost' => 'decimal:4',
        'actual_cost' => 'decimal:4',
        'price_variance' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
