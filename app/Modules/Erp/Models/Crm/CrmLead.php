<?php

namespace App\Modules\Erp\Models\Crm;

use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLead extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['lead_no', 'company_name', 'contact_name', 'phone', 'email', 'source', 'status', 'owner_id', 'remark'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CrmFollowUp::class, 'lead_id');
    }
}
