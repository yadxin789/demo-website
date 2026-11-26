<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inspection 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class Inspection extends BaseModel
{
    protected $fillable = ['inspection_no', 'work_order_id', 'operation_id', 'template_id', 'type', 'result', 'inspector_id', 'inspected_at', 'remark'];

    protected $casts = ['inspected_at' => 'datetime'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(WorkOrderOperation::class, 'operation_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }
}