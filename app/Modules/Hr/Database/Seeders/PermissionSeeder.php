<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * HR 权限字典（H0）。
 *
 * 命名约定：{域}.{动作}，全部挂在 guard = hr，不与 ERP/MES 的 permission 混用。
 */
class PermissionSeeder extends Seeder
{
    /** 权限名 => 中文说明 */
    public const PERMISSIONS = [
        'org.view' => '查看组织与编制',
        'org.manage' => '维护部门 / 岗位 / 编制',
        'employee.view' => '查看员工档案',
        'employee.manage' => '维护员工档案与合同异动',
        'attendance.view' => '查看考勤',
        'attendance.manage' => '处理考勤异常与加班审批',
        'leave.view' => '查看请假',
        'leave.approve' => '审批请假',
        'payroll.view' => '查看工资批次',
        'payroll.manage' => '核算与确认工资',
        'recruit.view' => '查看招聘',
        'recruit.manage' => '维护招聘需求与候选人',
        'performance.view' => '查看绩效',
        'performance.manage' => '绩效周期与评分',
        'report.view' => '查看报表',
        'import.run' => '执行 Excel 导入导出',
        'system.manage' => '系统与账号管理',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'hr');
        }
    }
}
