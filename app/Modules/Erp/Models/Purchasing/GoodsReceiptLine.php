<?php

namespace App\Modules\Erp\Models\Purchasing;

use App\Modules\Erp\Models\MasterData\Material;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'receipt_id', 'material_id', 'lot_no', 'qty',
        'qualified_qty', 'rejected_qty', 'supplier_batch',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'qualified_qty' => 'decimal:2',
        'rejected_qty' => 'decimal:2',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'receipt_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
