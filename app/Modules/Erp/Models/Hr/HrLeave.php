<?php

namespace App\Modules\Erp\Models\Hr;


class HrLeave extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['leave_no', 'employee_id', 'type', 'from_date', 'to_date', 'days', 'reason', 'status'];
    protected $casts = ['from_date' => 'date', 'to_date' => 'date'];
}
