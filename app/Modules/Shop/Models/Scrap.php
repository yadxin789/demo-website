<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scrap extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public const LOSS_EXPENSE = 'expense';
    public const LOSS_OTHER = 'other';

    protected $fillable = [
        'code', 'warehouse_id', 'scrap_date', 'status', 'loss_type',
        'reason', 'operator_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'operator_id' => 'integer',
            'scrap_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ScrapLine::class, 'scrap_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
