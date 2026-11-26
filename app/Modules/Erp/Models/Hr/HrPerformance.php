<?php

namespace App\Modules\Erp\Models\Hr;


class HrPerformance extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['employee_id', 'period', 'score', 'grade', 'comment'];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
