<?php

namespace App\Modules\Pm\Models;


class ExtensionApplication extends BaseModel
{
    protected $fillable = [
        'task_id', 'applicant_id', 'approver_id', 'days', 'reason',
        'status', 'approved_at', 'reject_reason'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}