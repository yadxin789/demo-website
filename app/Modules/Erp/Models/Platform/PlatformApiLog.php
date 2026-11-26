<?php

namespace App\Modules\Erp\Models\Platform;


class PlatformApiLog extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['platform', 'api_path', 'method', 'http_code', 'duration_ms', 'request_body', 'response_body', 'called_at'];
    protected $casts = ['called_at' => 'datetime'];
}
