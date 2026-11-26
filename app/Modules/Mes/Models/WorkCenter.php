<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WorkCenter 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class WorkCenter extends BaseModel
{
    protected $fillable = ['code', 'name', 'workshop', 'daily_capacity', 'status'];

    protected $casts = ['daily_capacity' => 'integer'];

    public function operations(): HasMany
    {
        return $this->hasMany(WorkOrderOperation::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }
}