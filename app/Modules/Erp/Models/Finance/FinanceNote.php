<?php

namespace App\Modules\Erp\Models\Finance;


class FinanceNote extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['note_no', 'type', 'amount', 'drawer', 'due_date', 'status'];

    protected $casts = ['due_date' => 'date'];
}
