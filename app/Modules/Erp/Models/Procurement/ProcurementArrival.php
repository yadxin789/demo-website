<?php

namespace App\Modules\Erp\Models\Procurement;

use App\Modules\Erp\Models\MasterData\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementArrival extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['arrival_no', 'plan_id', 'supplier_id', 'arrival_date', 'status'];
    protected $casts = ['arrival_date' => 'date'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProcurementPlan::class, 'plan_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
