<?php

namespace App\Modules\Pm\Models;


class TaskType extends BaseModel
{
    protected $fillable = ['name', 'description', 'department_id', 'is_required', 'sort_order'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}