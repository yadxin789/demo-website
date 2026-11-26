<?php

namespace App\Modules\Erp\Models\Production;

use App\Modules\Erp\Models\Inventory\Warehouse;
use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialIssue extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['issue_no', 'production_order_id', 'warehouse_id', 'status', 'issue_date', 'created_by'];
    protected $casts = ['issue_date' => 'date'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialIssueLine::class, 'material_issue_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
