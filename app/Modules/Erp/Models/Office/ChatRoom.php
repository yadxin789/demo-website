<?php

namespace App\Modules\Erp\Models\Office;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends \App\Modules\Erp\Models\BaseModel
{
    protected $table = 'system_chat_rooms';
    protected $fillable = ['room_name', 'room_type', 'related_id', 'creator_id'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'room_id')->orderBy('id');
    }
}
