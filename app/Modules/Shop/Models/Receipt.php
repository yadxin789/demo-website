<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code', 'purchase_order_id', 'supplier_id', 'warehouse_id',
        'receipt_date', 'status', 'operator_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'supplier_id' => 'integer',
            'warehouse_id' => 'integer',
            'operator_id' => 'integer',
            'receipt_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class, 'receipt_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
