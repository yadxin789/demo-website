<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankStatement extends BaseModel
{
    public const DIRECTION_RECEIVE = 'receive';
    public const DIRECTION_PAY = 'pay';

    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_MATCHED = 'matched';

    protected $fillable = [
        'account_id', 'statement_date', 'direction', 'amount', 'counterparty',
        'reference', 'memo', 'status', 'operator_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'amount' => 'decimal:4',
            'statement_date' => 'date',
            'operator_id' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(AccountReconciliation::class, 'statement_id');
    }
}
