<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EquipmentCheck 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class EquipmentCheck extends BaseModel
{
    protected $fillable = ['equipment_id', 'checked_at', 'checker_id', 'result', 'remark'];

    protected $casts = ['checked_at' => 'datetime'];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }
}