<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MaintenanceOrder 保养/维修工单（MES，M7 增补）。
 *
 * pm（计划保养）由计划到期自动生成，cm（故障维修）由设备报修转来。
 */
class MaintenanceOrder extends BaseModel
{
    protected $fillable = ['order_no', 'plan_id', 'equipment_id', 'type', 'status', 'due_at', 'done_at', 'assignee_id', 'cost_hours', 'remark'];

    protected $casts = ['due_at' => 'date', 'done_at' => 'datetime', 'cost_hours' => 'decimal:2'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'plan_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
