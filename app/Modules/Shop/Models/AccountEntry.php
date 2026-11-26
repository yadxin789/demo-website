<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountEntry extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'code', 'journal_id', 'entry_date', 'status', 'source_type', 'source_id',
        'source_code', 'description', 'creator_id', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'journal_id' => 'integer',
            'source_id' => 'integer',
            'creator_id' => 'integer',
            'entry_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountEntryLine::class, 'entry_id')->orderBy('sort');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function totalDebit(): float
    {
        return (float) $this->lines->where('direction', AccountEntryLine::DIRECTION_DEBIT)->sum('amount');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines->where('direction', AccountEntryLine::DIRECTION_CREDIT)->sum('amount');
    }

    public function isBalanced(): bool
    {
        return round($this->totalDebit(), 4) === round($this->totalCredit(), 4);
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_POSTED, self::STATUS_REVERSED], true);
    }
}
