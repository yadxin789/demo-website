<?php

namespace App\Modules\Erp\Models\MasterData;

use App\Modules\Erp\Models\Concerns\BelongsToTenant;
use App\Modules\Erp\Models\Concerns\HasDataScope;

class Customer extends \App\Modules\Erp\Models\BaseModel
{
    use BelongsToTenant, HasDataScope;

    // P0-08：客户归销售所有（owner_id）+ 归属部门
    protected static ?string $dataScopeOwnerColumn = 'owner_id';
    protected static ?string $dataScopeDepartmentColumn = 'department_id';

    protected $fillable = [
        'customer_code', 'customer_name', 'contact_person', 'contact_phone',
        'email', 'address', 'tax_number', 'payment_terms', 'credit_limit', 'status',
        'owner_id', 'department_id', 'tenant_id',
    ];

    protected $casts = [
        'status' => 'integer',
        'credit_limit' => 'decimal:2',
    ];

    /** 仅启用客户 */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
