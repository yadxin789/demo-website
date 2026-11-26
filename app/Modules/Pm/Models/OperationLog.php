<?php

namespace App\Modules\Pm\Models;


class OperationLog extends BaseModel
{
    protected $fillable = ['user_id', 'ip', 'module', 'action', 'content'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}