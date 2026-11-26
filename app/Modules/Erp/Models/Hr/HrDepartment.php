<?php

namespace App\Modules\Erp\Models\Hr;


/**
 * @deprecated HR 部门视图与 Organization\Department 概念重叠。
 *             新代码请使用 App\Modules\Erp\Models\Organization\Department（表 departments）。
 *             本 Model 暂保留以兼容 hr_departments 表的历史数据，后续再做数据合并。
 */
class HrDepartment extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['code', 'name', 'parent_id', 'manager'];
}
