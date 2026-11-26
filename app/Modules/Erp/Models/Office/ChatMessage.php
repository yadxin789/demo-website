<?php

namespace App\Modules\Erp\Models\Office;

use App\Modules\Erp\Models\Concerns\RecordsDeletedBy;
use App\Modules\Erp\Models\Organization\User;
use App\Modules\Erp\Models\System\MediaFile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends \App\Modules\Erp\Models\BaseModel
{
    use SoftDeletes;
    use RecordsDeletedBy;

    public $timestamps = false;
    protected $table = 'system_chat_messages';
    protected $fillable = ['room_id', 'sender_id', 'msg_type', 'content', 'file_id', 'created_at', 'deleted_by'];
    protected $casts = ['created_at' => 'datetime'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'file_id');
    }
}
