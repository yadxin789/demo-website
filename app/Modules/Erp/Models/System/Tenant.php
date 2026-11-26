<?php

namespace App\Modules\Erp\Models\System;


class Tenant extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'domain', 'contact_name', 'contact_phone', 'expired_at', 'status', 'settings'];
    protected $casts = ['expired_at' => 'date', 'settings' => 'array'];
}
