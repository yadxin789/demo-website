<?php

namespace App\Modules\Pm\Models;


class AiMessage extends BaseModel
{
    protected $table = 'ai_messages';

    protected $fillable = [
        'conversation_id', 'role', 'content', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function attachments()
    {
        return $this->hasMany(AiAttachment::class, 'message_id')->orderBy('id');
    }
}
