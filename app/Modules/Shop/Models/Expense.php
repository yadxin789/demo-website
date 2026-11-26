<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    public const SETTLE_PAYABLE = 'payable';
    public const SETTLE_CASH = 'cash';
    public const SETTLE_BANK = 'bank';

    protected $fillable = [
        'code', 'expense_date', 'expense_account_id', 'partner_id', 'payee', 'department',
        'settlement_method', 'payment_account_id', 'amount_net', 'tax_amount', 'total_amount',
        'status', 'description', 'reference', 'creator_id', 'entry_id', 'paid_entry_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'expense_account_id' => 'integer',
            'partner_id' => 'integer',
            'payment_account_id' => 'integer',
            'creator_id' => 'integer',
            'entry_id' => 'integer',
            'paid_entry_id' => 'integer',
            'amount_net' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'partner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountEntry::class, 'entry_id');
    }

    public function paidEntry(): BelongsTo
    {
        return $this->belongsTo(AccountEntry::class, 'paid_entry_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class, 'expense_id');
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
