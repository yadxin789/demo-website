<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProductionReport 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class ProductionReport extends BaseModel
{
    protected $fillable = ['work_order_id', 'operation_id', 'user_id', 'shift_id', 'ok_qty', 'defect_qty', 'minutes', 'reported_at', 'remark'];

    protected $casts = ['ok_qty' => 'decimal:3', 'defect_qty' => 'decimal:3', 'minutes' => 'integer', 'reported_at' => 'datetime'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(WorkOrderOperation::class, 'operation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}