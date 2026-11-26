<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COUNTING = 'counting';
    public const STATUS_ADJUSTED = 'adjusted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code', 'warehouse_id', 'location_id', 'count_date', 'status', 'operator_id', 'entry_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'location_id' => 'integer',
            'operator_id' => 'integer',
            'entry_id' => 'integer',
            'count_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class, 'count_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountEntry::class, 'entry_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_COUNTING], true);
    }
}
