<?php

namespace App\Modules\Erp\Models\System;


class WecomMessageLog extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['msg_id', 'to_user', 'msg_type', 'content', 'status', 'error_msg', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
}
