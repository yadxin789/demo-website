<?php

namespace App\Modules\Erp\Models\Procurement;


class ProcurementPlan extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['plan_no', 'title', 'plan_date', 'total_amount', 'status'];
    protected $casts = ['plan_date' => 'date'];
}
