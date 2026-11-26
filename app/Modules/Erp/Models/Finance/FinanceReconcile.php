<?php

namespace App\Modules\Erp\Models\Finance;


class FinanceReconcile extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['reconcile_no', 'period', 'type', 'book_amount', 'actual_amount', 'diff_amount', 'status'];
    protected $casts = ['period' => 'date'];
}
