<?php

namespace App\Modules\Erp\Models\Inventory;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InventoryStatusChange extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'material_id', 'lot_no', 'from_status', 'to_status', 'qty',
        'reason', 'approval_no', 'operator_id', 'approver_id', 'created_at',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
