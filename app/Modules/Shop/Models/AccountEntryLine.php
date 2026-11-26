<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountEntryLine extends BaseModel
{
    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'entry_id', 'account_id', 'direction', 'amount', 'tax_id', 'partner_id', 'memo', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'entry_id' => 'integer',
            'account_id' => 'integer',
            'tax_id' => 'integer',
            'partner_id' => 'integer',
            'amount' => 'decimal:4',
            'sort' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountEntry::class, 'entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
