<?php

namespace App\Modules\Erp\Models\Production;

use App\Modules\Erp\Models\Bom\BomHeader;
use App\Modules\Erp\Models\Concerns\HasDataScope;
use App\Modules\Erp\Models\MasterData\Material;
use App\Modules\Erp\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends \App\Modules\Erp\Models\BaseModel
{
    use HasDataScope;

    // P0-08：工单归生产部 + 创建人
    protected static ?string $dataScopeOwnerColumn = 'created_by';
    protected static ?string $dataScopeDepartmentColumn = 'department_id';

    protected $fillable = [
        'order_no', 'product_material_id', 'bom_id', 'routing_id', 'plan_qty',
        'completed_qty', 'defective_qty', 'status', 'start_date', 'end_date',
        'sales_order_id', 'remark', 'department_id', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'plan_qty' => 'decimal:2',
        'completed_qty' => 'decimal:2',
        'defective_qty' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BomHeader::class, 'bom_id');
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(ProductionOperationLog::class, 'production_order_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class, 'production_order_id');
    }
}
