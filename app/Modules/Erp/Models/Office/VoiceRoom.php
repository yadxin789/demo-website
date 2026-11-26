<?php

namespace App\Modules\Erp\Models\Office;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceRoom extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['room_no', 'name', 'host_id', 'status', 'started_at', 'ended_at'];
    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function host(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Erp\Models\Organization\User::class, 'host_id');
    }
}
