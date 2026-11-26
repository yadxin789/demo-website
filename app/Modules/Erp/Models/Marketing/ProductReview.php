<?php

namespace App\Modules\Erp\Models\Marketing;


class ProductReview extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['spu_id', 'buyer_nick', 'rating', 'content', 'is_hidden'];
}
