<?php

namespace App\Modules\Erp\Models\Rnd;


/**
 * 会议信令（WebRTC SDP / ICE candidate），按"发给谁"落库，参与者轮询领取。
 */
class RndMeetingSignal extends \App\Modules\Erp\Models\BaseModel
{
    protected $table = 'rnd_meeting_signals';

    public $timestamps = false;

    protected $fillable = ['meeting_id', 'from_user_id', 'to_user_id', 'type', 'payload', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
