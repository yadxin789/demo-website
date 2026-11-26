<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RoutingOperation 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class RoutingOperation extends BaseModel
{
    protected $fillable = ['routing_id', 'op_no', 'op_name', 'work_center_id', 'std_minutes', 'inspection_required'];

    protected $casts = ['std_minutes' => 'decimal:2', 'inspection_required' => 'bool'];

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }
}