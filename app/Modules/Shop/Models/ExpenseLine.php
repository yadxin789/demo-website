<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseLine extends BaseModel
{
    protected $fillable = [
        'expense_id', 'description', 'amount_net', 'tax_amount', 'total_amount', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'expense_id' => 'integer',
            'amount_net' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'sort' => 'integer',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }
}
