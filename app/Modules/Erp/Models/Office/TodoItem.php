<?php

namespace App\Modules\Erp\Models\Office;


class TodoItem extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $table = 'system_todo_items';
    protected $fillable = ['user_id', 'todo_type', 'title', 'deadline', 'is_finished', 'business_type', 'business_id', 'created_at', 'finished_at'];
    protected $casts = ['deadline' => 'datetime', 'is_finished' => 'boolean', 'created_at' => 'datetime', 'finished_at' => 'datetime'];
}
