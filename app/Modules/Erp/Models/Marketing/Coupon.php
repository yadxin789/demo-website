<?php

namespace App\Modules\Erp\Models\Marketing;


class Coupon extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['coupon_no', 'name', 'amount', 'min_spend', 'total_qty', 'received_qty', 'expire_date', 'status'];
    protected $casts = ['expire_date' => 'date'];
}
