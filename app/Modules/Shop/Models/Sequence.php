<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Sequence extends BaseModel
{
    public const PERIOD_NONE = 'none';
    public const PERIOD_DAY = 'day';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_YEAR = 'year';

    protected $fillable = [
        'code', 'name', 'prefix', 'suffix', 'padding',
        'next_number', 'period_mode', 'last_reset_at',
    ];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'next_number' => 'integer',
            'last_reset_at' => 'date',
        ];
    }
}
