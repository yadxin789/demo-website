<?php

namespace App\Modules\Erp\Models\Hr;


class HrRecruitment extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['req_no', 'position', 'headcount', 'department', 'requirement', 'status'];
}
