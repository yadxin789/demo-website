<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 排班（人 × 班次 × 日期）。 */
class ShiftAssignment extends BaseModel
{
    protected $fillable = ['employee_id', 'shift_id', 'work_date', 'status', 'remark'];

    protected $casts = ['work_date' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
