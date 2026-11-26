<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 小票模板：抬头、地址、电话、页脚与打印项开关。
 * 只有启用（is_active）的模板生效，未配置时由服务回落到内置默认值。
 */
class ReceiptTemplate extends BaseModel
{
    protected $table = 'receipt_templates';

    protected $fillable = [
        'name', 'store_name', 'address', 'phone', 'footer',
        'show_discount', 'show_cashier', 'show_barcode', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'show_discount' => 'boolean',
            'show_cashier' => 'boolean',
            'show_barcode' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
