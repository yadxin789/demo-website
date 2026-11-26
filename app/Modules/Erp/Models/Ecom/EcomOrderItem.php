<?php

namespace App\Modules\Erp\Models\Ecom;


class EcomOrderItem extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;

    protected $fillable = ['ecom_order_id', 'outer_sku', 'product_name', 'material_id', 'sku_mapped', 'qty', 'price'];

    protected $casts = [
        'sku_mapped' => 'boolean',
        'qty' => 'integer',
        'price' => 'decimal:2',
    ];
}
