<?php

namespace App\Modules\Erp\Models\Rnd;

use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndExtensionApplication extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $fillable = ['task_id', 'applicant_id', 'apply_days', 'reason', 'status', 'approver_id', 'approved_at'];
    protected $casts = ['approved_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(RndProjectTask::class, 'task_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /** 审批人（研发经理，审批流通过后回写） */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
