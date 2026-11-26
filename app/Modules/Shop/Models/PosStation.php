<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosStation extends BaseModel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'code', 'name', 'warehouse_id', 'default_location_id', 'payment_method_ids',
        'receipt_template', 'auto_print_receipt', 'printer_driver', 'printer_address', 'printer_port',
        'paper_width', 'encoding', 'cash_drawer_pin', 'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'paper_width' => 58,
        'auto_print_receipt' => false,
    ];

    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'default_location_id' => 'integer',
            'payment_method_ids' => 'array',
            'paper_width' => 'integer',
            'auto_print_receipt' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function allowsPaymentMethod(int $methodId): bool
    {
        $methods = $this->payment_method_ids ?? [];

        return $methods === [] || in_array($methodId, $methods, true);
    }
}
