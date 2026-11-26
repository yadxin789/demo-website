<?php

namespace App\Modules\Erp\Models\Hr;


class HrAttendance extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['employee_id', 'work_date', 'check_in', 'check_out', 'status'];
    protected $casts = ['work_date' => 'date', 'check_in' => 'datetime', 'check_out' => 'datetime'];
}
