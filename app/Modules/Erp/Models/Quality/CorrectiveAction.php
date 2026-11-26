<?php

namespace App\Modules\Erp\Models\Quality;

use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectiveAction extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['ca_no', 'inspection_record_id', 'problem', 'root_cause', 'action', 'owner_id', 'due_date', 'status'];
    protected $casts = ['due_date' => 'date'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function inspectionRecord(): BelongsTo
    {
        return $this->belongsTo(InspectionRecord::class, 'inspection_record_id');
    }
}
