<?php

namespace App\Modules\Erp\Models\Security;


class LoginLog extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $table = 'login_logs';
    protected $fillable = ['user_id', 'username', 'ip_address', 'user_agent', 'status', 'fail_reason', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
