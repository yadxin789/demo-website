<?php

namespace App\Modules\Erp\Models\Ecom;


class EcomPlatform extends \App\Modules\Erp\Models\BaseModel
{
    // app_secret / access_token / refresh_token 为敏感凭据，禁止批量赋值
    protected $fillable = ['code', 'name', 'app_key', 'shop_url', 'status'];

    protected $hidden = ['app_secret', 'access_token', 'refresh_token'];

    protected $casts = [
        'status' => 'boolean',
        'app_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];
}
