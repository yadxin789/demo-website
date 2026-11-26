<?php

namespace App\Modules\Erp\Models\Security;


class LoginFailure extends \App\Modules\Erp\Models\BaseModel
{
    protected $table = 'login_failures';
    protected $fillable = ['username', 'ip_address', 'fail_count', 'locked_until'];
    protected $casts = ['locked_until' => 'datetime'];
}
