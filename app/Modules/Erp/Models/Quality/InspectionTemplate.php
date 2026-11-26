<?php

namespace App\Modules\Erp\Models\Quality;


class InspectionTemplate extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'stage', 'check_items', 'standard', 'status'];
    protected $casts = ['check_items' => 'array', 'status' => 'boolean'];
}
