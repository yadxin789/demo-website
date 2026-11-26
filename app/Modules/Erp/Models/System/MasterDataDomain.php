<?php

namespace App\Modules\Erp\Models\System;

use App\Modules\Erp\Models\Organization\Department;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 基础数据归口台账：每一类基础数据只对应一个归口部门。
 * 归口部门负责口径，管理员负责系统维护。
 */
class MasterDataDomain extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'code', 'name', 'owner_department_id', 'route_name', 'description', 'status', 'sort_order',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function ownerDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'owner_department_id');
    }
}
