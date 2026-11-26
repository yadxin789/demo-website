<?php

declare(strict_types=1);

namespace App\Modules\Mes\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * MES 权限字典（M0/M1）。
 *
 * 命名约定：{域}.{动作}，全部挂在 guard = mes，不与 ERP 的 permission 混用。
 */
class PermissionSeeder extends Seeder
{
    /** 权限名 => 中文说明 */
    public const PERMISSIONS = [
        'master.view' => '查看主数据',
        'master.manage' => '维护主数据',
        'work-order.view' => '查看工单',
        'work-order.create' => '创建工单',
        'work-order.release' => '下达工单',
        'work-order.dispatch' => '派工',
        'report.create' => '生产报工',
        'issue.manage' => '物料领用',
        'quality.view' => '查看检验',
        'quality.manage' => '检验判定与不良处置',
        'equipment.view' => '查看设备',
        'equipment.manage' => '设备点检与报修',
        'import.run' => '执行 Excel 导入',
        'system.manage' => '系统与账号管理',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'mes');
        }
    }
}
