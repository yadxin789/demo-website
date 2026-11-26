<?php

namespace App\Modules\Erp\Models\Production;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingOperation extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $fillable = ['routing_id', 'operation_seq', 'operation_name', 'work_center_id', 'standard_hours', 'description'];

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }
}
