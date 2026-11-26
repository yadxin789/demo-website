<?php

namespace App\Modules\Erp\Models\Product;


class ProductCategory extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'parent_id', 'sort'];
}
