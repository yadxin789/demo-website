<?php

namespace App\Modules\Erp\Models\Approval;

use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalInstance extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['flow_id', 'business_type', 'business_id', 'current_step', 'status', 'applicant_id', 'logs_json', 'payload_json', 'delegated', 'approved_at'];
    protected $casts = ['logs_json' => 'array', 'payload_json' => 'array', 'delegated' => 'boolean', 'approved_at' => 'datetime'];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'flow_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }
}
