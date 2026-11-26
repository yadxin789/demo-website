<?php

namespace App\Modules\Erp\Models\Approval;


class ApprovalFlow extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $fillable = ['flow_code', 'flow_name', 'business_type', 'steps_json', 'status'];
    protected $casts = ['steps_json' => 'array', 'status' => 'boolean'];
}
