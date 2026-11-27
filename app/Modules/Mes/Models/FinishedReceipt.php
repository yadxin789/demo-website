<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FinishedReceipt 完工入库单（MES，M7 增补）。
 *
 * 一张工单可以分批入库；每批带一个批次号，批次再进 lots 表参与追溯。
 */
class FinishedReceipt extends BaseModel
{
    protected $fillable = ['receipt_no', 'work_order_id', 'material_id', 'lot_no', 'qty', 'warehouse', 'received_by', 'received_at', 'remark'];

    protected $casts = ['qty' => 'decimal:3', 'received_at' => 'datetime'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
