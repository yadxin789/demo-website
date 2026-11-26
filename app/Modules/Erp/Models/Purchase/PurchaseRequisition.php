<?php

namespace App\Modules\Erp\Models\Purchase;

use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @deprecated 本目录为历史命名，新代码请使用 App\Modules\Erp\Models\Purchasing\* 命名空间。
 *             本 Model 暂保留以兼容现有代码，后续迭代再做物理迁移。
 */
class PurchaseRequisition extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['req_no', 'title', 'department_id', 'requester_id', 'need_date', 'status', 'remark', 'approver_id', 'approved_at'];
    protected $casts = ['need_date' => 'date', 'approved_at' => 'datetime'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class, 'requisition_id');
    }
}
