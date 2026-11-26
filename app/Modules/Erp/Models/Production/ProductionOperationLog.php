<?php

namespace App\Modules\Erp\Models\Production;

use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperationLog extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $fillable = ['production_order_id', 'operation_name', 'worker_id', 'good_qty', 'defective_qty', 'defect_code', 'work_hours', 'reported_at', 'remark'];
    protected $casts = ['reported_at' => 'datetime'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
}
