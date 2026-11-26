<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPayment extends BaseModel
{
    protected $fillable = [
        'order_id', 'session_id', 'method_id', 'amount', 'reference', 'paid_at', 'operator_id',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'session_id' => 'integer',
            'method_id' => 'integer',
            'operator_id' => 'integer',
            'amount' => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'method_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
