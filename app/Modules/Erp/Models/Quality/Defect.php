<?php

namespace App\Modules\Erp\Models\Quality;


class Defect extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'category', 'description'];
}
