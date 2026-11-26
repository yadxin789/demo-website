<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

/**
 * InspectionTemplate 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class InspectionTemplate extends BaseModel
{
    protected $fillable = ['code', 'name', 'op_no', 'type', 'items', 'is_active'];

    protected $casts = ['items' => 'array', 'is_active' => 'bool', 'op_no' => 'integer'];
}