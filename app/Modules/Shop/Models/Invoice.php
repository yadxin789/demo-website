<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'code', 'partner_id', 'entry_id', 'invoice_date', 'due_date', 'status',
        'total_amount', 'paid_amount', 'source_type', 'source_id',
    ];

    protected function casts(): array
    {
        return [
            'partner_id' => 'integer',
            'entry_id' => 'integer',
            'source_id' => 'integer',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:4',
            'paid_amount' => 'decimal:4',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'partner_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountEntry::class, 'entry_id');
    }

    public function balance(): float
    {
        return max(0.0, (float) $this->total_amount - (float) $this->paid_amount);
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function canVoid(): bool
    {
        return ! in_array($this->status, [self::STATUS_PAID, self::STATUS_VOID], true);
    }

    /** 根据已收金额回推未付/部分/已付状态；作废单不覆盖。 */
    public function recalcStatus(): void
    {
        if ($this->isVoid()) {
            return;
        }

        $paid = (float) $this->paid_amount;
        $total = (float) $this->total_amount;

        $this->status = match (true) {
            $paid <= 0 => self::STATUS_OPEN,
            round($paid - $total, 4) >= 0 => self::STATUS_PAID,
            default => self::STATUS_PARTIAL,
        };
    }
}
