<?php

namespace App\Modules\Erp\Models\Hr;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeave extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['leave_no', 'employee_id', 'type', 'from_date', 'to_date', 'days', 'reason', 'status'];
    protected $casts = ['from_date' => 'date', 'to_date' => 'date'];

    /** 请假单所属员工（列表页显示员工姓名，需 eager load） */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
