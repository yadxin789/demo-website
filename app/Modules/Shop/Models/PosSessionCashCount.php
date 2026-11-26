<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSessionCashCount extends BaseModel
{
    protected $fillable = [
        'session_id', 'denomination', 'count_qty', 'count_amount', 'counted_by', 'counted_at',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'denomination' => 'decimal:4',
            'count_qty' => 'integer',
            'count_amount' => 'decimal:4',
            'counted_by' => 'integer',
            'counted_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
