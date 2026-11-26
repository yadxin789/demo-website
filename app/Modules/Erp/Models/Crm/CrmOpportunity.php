<?php

namespace App\Modules\Erp\Models\Crm;

use App\Modules\Erp\Models\MasterData\Customer;
use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmOpportunity extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['op_no', 'name', 'lead_id', 'customer_id', 'expected_amount', 'probability', 'expected_close_date', 'stage', 'owner_id'];
    protected $casts = ['expected_close_date' => 'date', 'expected_amount' => 'decimal:2'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
