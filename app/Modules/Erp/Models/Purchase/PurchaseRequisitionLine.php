<?php

namespace App\Modules\Erp\Models\Purchase;

use App\Modules\Erp\Models\MasterData\Material;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @deprecated 本目录为历史命名，新代码请使用 App\Modules\Erp\Models\Purchasing\* 命名空间。
 */
class PurchaseRequisitionLine extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $fillable = ['requisition_id', 'material_id', 'qty', 'unit', 'estimated_price', 'remark'];
    protected $casts = ['qty' => 'decimal:3', 'estimated_price' => 'decimal:4'];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
