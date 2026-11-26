<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BomLine 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class BomLine extends BaseModel
{
    protected $fillable = ['bom_id', 'component_material_id', 'qty_per', 'loss_rate', 'seq'];

    protected $casts = ['qty_per' => 'decimal:4', 'loss_rate' => 'decimal:4'];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'component_material_id');
    }
}