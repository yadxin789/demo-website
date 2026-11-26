<?php

namespace App\Modules\Erp\Models\Finance;


class PaymentTerm extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'days', 'description', 'status'];
    protected $casts = ['days' => 'integer', 'status' => 'boolean'];
}
