<?php

namespace App\Modules\Erp\Models\System;


class Unit extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'unit_code', 'unit_name', 'conversion_factor', 'status',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'status' => 'boolean',
    ];
}
