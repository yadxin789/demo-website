<?php

namespace App\Modules\Erp\Models\Security;


class SensitiveAccessLog extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $table = 'sensitive_access_logs';
    protected $fillable = ['user_id', 'action', 'target', 'ip_address', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
