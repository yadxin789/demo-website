<?php

namespace App\Modules\Erp\Models\Platform;


class PlatformStatusMapping extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['platform', 'platform_status', 'local_status'];
}
