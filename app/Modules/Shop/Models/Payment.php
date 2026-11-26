<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends BaseModel
{
    public const DIRECTION_RECEIVE = 'receive';
    public const DIRECTION_PAY = 'pay';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'code', 'partner_id', 'direction', 'method_id', 'account_id', 'amount',
        'payment_date', 'reference', 'status', 'entry_id', 'operator_id',
    ];

    protected function casts(): array
    {
        return [
            'partner_id' => 'integer',
            'method_id' => 'integer',
            'account_id' => 'integer',
            'entry_id' => 'integer',
            'operator_id' => 'integer',
            'payment_date' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'method_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountEntry::class, 'entry_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function isReceive(): bool
    {
        return $this->direction === self::DIRECTION_RECEIVE;
    }
}
