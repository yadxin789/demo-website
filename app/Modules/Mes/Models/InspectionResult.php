<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * InspectionResult 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class InspectionResult extends BaseModel
{
    protected $fillable = ['inspection_id', 'item_name', 'std_value', 'actual_value', 'verdict'];

    protected $casts = [];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
}