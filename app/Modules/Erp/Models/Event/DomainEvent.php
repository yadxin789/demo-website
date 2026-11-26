<?php

namespace App\Modules\Erp\Models\Event;


class DomainEvent extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['name', 'agg_type', 'agg_id', 'data'];
    protected $casts = ['data' => 'array'];
}
