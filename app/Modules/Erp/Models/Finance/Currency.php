<?php

namespace App\Modules\Erp\Models\Finance;


class Currency extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'rate', 'is_base'];
}
