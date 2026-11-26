<?php

namespace App\Modules\Erp\Models\Finance;

use App\Modules\Erp\Models\Concerns\HasDataScope;
use App\Modules\Erp\Models\MasterData\Supplier;
use App\Modules\Erp\Models\Purchasing\PurchaseOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBill extends \App\Modules\Erp\Models\BaseModel
{
    use HasDataScope;

    // P0-08：应付账单按采购责任人归属（owner_id），buyer_id 记录业务员
    protected static ?string $dataScopeOwnerColumn = 'owner_id';

    protected $fillable = ['bill_no', 'supplier_id', 'purchase_order_id', 'bill_date', 'due_date', 'amount', 'paid_amount', 'status', 'owner_id', 'buyer_id'];
    protected $casts = ['bill_date' => 'date', 'due_date' => 'date'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
