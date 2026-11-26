<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Defect 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class Defect extends BaseModel
{
    protected $fillable = ['work_order_id', 'operation_id', 'defect_code', 'qty', 'disposal', 'cause', 'owner_id', 'status'];

    protected $casts = ['qty' => 'decimal:3'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(WorkOrderOperation::class, 'operation_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}