<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WorkOrder 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class WorkOrder extends BaseModel
{
    protected $fillable = ['order_no', 'product_material_id', 'bom_id', 'routing_id', 'plan_qty', 'done_qty', 'defect_qty', 'status', 'plan_start', 'plan_end', 'priority', 'customer', 'remark'];

    protected $casts = ['plan_qty' => 'decimal:3', 'done_qty' => 'decimal:3', 'defect_qty' => 'decimal:3', 'plan_start' => 'date', 'plan_end' => 'date', 'priority' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(WorkOrderOperation::class)->orderBy('op_no');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductionReport::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }

    /** 完成率（0–100，整数） */
    public function getProgressAttribute(): int
    {
        return $this->plan_qty > 0 ? (int) round((float) $this->done_qty / (float) $this->plan_qty * 100) : 0;
    }
}