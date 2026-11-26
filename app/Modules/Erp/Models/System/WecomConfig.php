<?php

namespace App\Modules\Erp\Models\System;


class WecomConfig extends \App\Modules\Erp\Models\BaseModel
{
    // app_secret / token / encoding_aes_key 为敏感凭据，禁止批量赋值
    protected $fillable = ['corp_id', 'agent_id', 'status'];

    protected $hidden = ['app_secret', 'token', 'encoding_aes_key'];

    protected $casts = [
        'status' => 'boolean',
        'app_secret' => 'encrypted',
        'token' => 'encrypted',
        'encoding_aes_key' => 'encrypted',
    ];
}
