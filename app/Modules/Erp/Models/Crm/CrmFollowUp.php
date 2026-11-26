<?php

namespace App\Modules\Erp\Models\Crm;

use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFollowUp extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['lead_id', 'opportunity_id', 'user_id', 'type', 'content', 'followed_at'];
    protected $casts = ['followed_at' => 'datetime'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
