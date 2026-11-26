<?php

namespace App\Modules\Erp\Models\Marketing;


class Promotion extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'type', 'value', 'start_date', 'end_date', 'status'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
}
