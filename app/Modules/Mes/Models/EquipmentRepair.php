<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EquipmentRepair 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class EquipmentRepair extends BaseModel
{
    protected $fillable = ['equipment_id', 'fault_desc', 'reported_by', 'handler_id', 'down_minutes', 'status', 'fixed_at'];

    protected $casts = ['fixed_at' => 'datetime', 'down_minutes' => 'integer'];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }
}