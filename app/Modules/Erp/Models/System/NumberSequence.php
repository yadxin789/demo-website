<?php

namespace App\Modules\Erp\Models\System;


class NumberSequence extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'rule_code', 'prefix', 'date_format', 'seq_length', 'current_seq', 'status', 'description',
    ];

    protected $casts = [
        'seq_length' => 'integer',
        'current_seq' => 'integer',
        'status' => 'boolean',
    ];
}
