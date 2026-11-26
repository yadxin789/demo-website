<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equipment 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class Equipment extends BaseModel
{
    /** Laravel 复数化会把 Equipment 变成 equipment（单数同形），这里显式指定表名 */
    protected $table = 'equipments';

    protected $fillable = ['code', 'name', 'work_center_id', 'status', 'check_cycle_days', 'last_check_at', 'purchased_on', 'remark'];

    protected $casts = ['last_check_at' => 'datetime', 'purchased_on' => 'date', 'check_cycle_days' => 'integer'];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(EquipmentCheck::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(EquipmentRepair::class);
    }
}