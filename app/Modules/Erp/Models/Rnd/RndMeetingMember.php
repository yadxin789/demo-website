<?php

namespace App\Modules\Erp\Models\Rnd;

use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndMeetingMember extends \App\Modules\Erp\Models\BaseModel
{
    protected $table = 'rnd_meeting_members';

    public $timestamps = false;

    protected $fillable = ['meeting_id', 'user_id', 'peer_id', 'joined_at', 'last_seen_at'];

    protected $casts = ['joined_at' => 'datetime', 'last_seen_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
