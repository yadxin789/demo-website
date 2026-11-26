<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ApsSchedule 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class ApsSchedule extends BaseModel
{
    protected $fillable = ['work_order_id', 'operation_id', 'work_center_id', 'start_at', 'end_at', 'seq'];

    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime', 'seq' => 'integer'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(WorkOrderOperation::class, 'operation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }
}