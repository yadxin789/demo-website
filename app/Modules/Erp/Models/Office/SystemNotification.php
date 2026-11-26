<?php

namespace App\Modules\Erp\Models\Office;


class SystemNotification extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $table = 'system_notifications';
    protected $fillable = ['user_id', 'notify_type', 'title', 'content', 'is_read', 'business_type', 'business_id', 'created_at'];
    protected $casts = ['is_read' => 'boolean', 'created_at' => 'datetime'];
}
