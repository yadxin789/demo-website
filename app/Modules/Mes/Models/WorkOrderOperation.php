<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WorkOrderOperation 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class WorkOrderOperation extends BaseModel
{
    protected $fillable = ['work_order_id', 'op_no', 'op_name', 'work_center_id', 'plan_qty', 'done_qty', 'defect_qty', 'status', 'assignee_id', 'team', 'std_minutes', 'started_at', 'finished_at'];

    protected $casts = ['plan_qty' => 'decimal:3', 'done_qty' => 'decimal:3', 'defect_qty' => 'decimal:3', 'std_minutes' => 'decimal:2', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductionReport::class, 'operation_id');
    }
}