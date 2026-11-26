<?php

namespace App\Modules\Erp\Models\Sales;

use App\Modules\Erp\Models\MasterData\Customer;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Shipment extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'shipment_no', 'sales_order_id', 'customer_id', 'shipment_date',
        'logistics_company', 'tracking_no', 'status', 'total_qty',
    ];

    protected $casts = [
        'shipment_date' => 'date',
        'total_qty' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
