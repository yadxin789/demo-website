<?php

namespace App\Modules\Erp\Models\Ai;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiMessage extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['conversation_id', 'role', 'content', 'token_usage', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AiAttachment::class, 'message_id')->orderBy('id');
    }
}
