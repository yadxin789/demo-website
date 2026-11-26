<?php

namespace App\Modules\Erp\Models\Finance;


class FinancialTransaction extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['tx_no', 'category', 'amount', 'direction', 'summary', 'tx_date'];
    protected $casts = ['tx_date' => 'date'];
}
