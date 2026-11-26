<?php

namespace App\Modules\Erp\Models\Hr;


class HrSalary extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['employee_id', 'period', 'base', 'bonus', 'deduction', 'net'];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
