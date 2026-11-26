<?php

namespace App\Modules\Pm\Models;


class Todo extends BaseModel
{
    protected $fillable = [
        'user_id', 'type', 'title', 'related_type', 'related_id', 'status', 'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function complete()
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }
}