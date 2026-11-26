<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Material 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class Material extends BaseModel
{
    protected $fillable = ['code', 'name', 'spec', 'unit', 'category', 'safety_stock', 'stock_qty', 'std_cost', 'is_active', 'remark'];

    protected $casts = ['safety_stock' => 'decimal:3', 'stock_qty' => 'decimal:3', 'std_cost' => 'decimal:4', 'is_active' => 'bool'];

    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class, 'product_material_id');
    }

    public function routings(): HasMany
    {
        return $this->hasMany(Routing::class, 'product_material_id');
    }
}