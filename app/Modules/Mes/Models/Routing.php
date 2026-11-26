<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Routing 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class Routing extends BaseModel
{
    protected $fillable = ['code', 'product_material_id', 'version', 'status'];

    protected $casts = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class)->orderBy('op_no');
    }
}