<?php

namespace App\Modules\Pm\Models;


class ChatClearState extends BaseModel
{
    protected $fillable = [
        'user_id',
        'project_id',
        'chat_type',
        'other_user_id',
        'conversation_key',
        'cleared_at',
    ];

    protected $casts = [
        'cleared_at' => 'datetime',
    ];
}
