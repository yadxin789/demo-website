<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MaterialIssue 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class MaterialIssue extends BaseModel
{
    protected $fillable = ['issue_no', 'work_order_id', 'status', 'issued_at', 'issued_by', 'remark'];

    protected $casts = ['issued_at' => 'datetime'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialIssueLine::class);
    }
}