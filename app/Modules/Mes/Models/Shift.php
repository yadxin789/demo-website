<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

/**
 * Shift 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class Shift extends BaseModel
{
    protected $fillable = ['code', 'name', 'start_time', 'end_time', 'cross_day', 'is_active'];

    protected $casts = ['cross_day' => 'bool', 'is_active' => 'bool'];
}