<?php

namespace App\Modules\Erp\Models\Ai;


class AiProvider extends \App\Modules\Erp\Models\BaseModel
{
    // 注意：api_key 为敏感凭据，不允许批量赋值
    protected $fillable = ['name', 'provider_code', 'api_base', 'model', 'status'];

    protected $hidden = ['api_key'];

    protected $casts = [
        'status' => 'boolean',
        'api_key' => 'encrypted',
    ];
}
