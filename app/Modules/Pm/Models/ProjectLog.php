<?php

namespace App\Modules\Pm\Models;


class ProjectLog extends BaseModel
{
    protected $fillable = ['project_id', 'user_id', 'action', 'content'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}