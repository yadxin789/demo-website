<?php

namespace App\Modules\Erp\Models\Product;


class ProductSku extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['spu_id', 'sku_code', 'spec', 'price', 'stock'];
}
