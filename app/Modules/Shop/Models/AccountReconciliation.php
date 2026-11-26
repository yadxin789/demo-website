<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountReconciliation extends BaseModel
{
    protected $fillable = [
        'statement_id', 'entry_line_id', 'account_id', 'amount', 'operator_id',
        'note', 'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'statement_id' => 'integer',
            'entry_line_id' => 'integer',
            'account_id' => 'integer',
            'amount' => 'decimal:4',
            'operator_id' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'statement_id');
    }

    public function entryLine(): BelongsTo
    {
        return $this->belongsTo(AccountEntryLine::class, 'entry_line_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
