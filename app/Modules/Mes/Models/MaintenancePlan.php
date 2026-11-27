<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MaintenancePlan 设备保养计划（MES，M7 增补）。
 *
 * 周期保养：cycle_days 到了就该做一次，last_done_at + cycle_days 推 next_due_at。
 */
class MaintenancePlan extends BaseModel
{
    protected $fillable = ['code', 'name', 'equipment_id', 'cycle_days', 'standard_minutes', 'last_done_at', 'next_due_at', 'is_active', 'remark'];

    protected $casts = ['cycle_days' => 'integer', 'standard_minutes' => 'integer', 'last_done_at' => 'date', 'next_due_at' => 'date', 'is_active' => 'boolean'];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'plan_id');
    }

    /** 是否已到期（含逾期） */
    public function getIsDueAttribute(): bool
    {
        return $this->next_due_at !== null && $this->next_due_at->lte(now()->startOfDay());
    }

    /** 逾期天数（未到期返回 0） */
    public function getOverdueDaysAttribute(): int
    {
        if ($this->next_due_at === null) {
            return 0;
        }

        $days = (int) $this->next_due_at->diffInDays(now()->startOfDay(), false);

        return $days > 0 ? $days : 0;
    }
}
