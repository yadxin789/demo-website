<?php

namespace App\Modules\Erp\Models\Security;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $table = 'security_events';
    protected $fillable = ['event_type', 'user_id', 'ip_address', 'detail', 'level', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Erp\Models\Organization\User::class, 'user_id');
    }
}
