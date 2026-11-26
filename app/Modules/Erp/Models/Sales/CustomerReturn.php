<?php

namespace App\Modules\Erp\Models\Sales;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Erp\Models\MasterData\Customer;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerReturn extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'return_no', 'sales_order_id', 'customer_id', 'return_reason',
        'status', 'total_qty', 'created_by', 'approved_by',
    ];

    protected $casts = [
        'total_qty' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerReturnLine::class, 'return_id');
    }
}
