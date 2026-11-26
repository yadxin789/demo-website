<?php

declare(strict_types=1);

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 本产品所有模型的唯一基类。
 *
 * 硬约束（G-02）：模型不得直接 extends Model，必须 extends 本类。
 * 原因：$connection 必须由基类强制指定，否则会落到 database.default（= erp），
 *      出现"PM 的数据写进 ERP 库"这种最难查的事故。
 */
abstract class BaseModel extends Model
{
    protected $connection = 'shop';

    protected $guarded = [];

    protected $perPage = 15;
}