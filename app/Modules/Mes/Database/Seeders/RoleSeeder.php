<?php

declare(strict_types=1);

namespace App\Modules\Mes\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * MES 五个角色（规划书 §5.1）。
 *
 * 角色与权限一一对应岗位：计划员、班组长、质检员、设备工程师、系统管理员。
 */
class RoleSeeder extends Seeder
{
    /** 角色名 => 中文名（用于界面显示） */
    public const ROLES = [
        'planner' => '生产计划员',
        'leader' => '班组长',
        'inspector' => '质检员',
        'equipment' => '设备工程师',
        'admin' => '系统管理员',
    ];

    /** 角色 => 权限（admin 后面单独给全量） */
    private const GRANTS = [
        'planner' => ['master.view', 'work-order.view', 'work-order.create', 'work-order.release'],
        'leader' => ['master.view', 'work-order.view', 'work-order.dispatch', 'report.create', 'issue.manage', 'quality.view'],
        'inspector' => ['work-order.view', 'quality.view', 'quality.manage'],
        'equipment' => ['equipment.view', 'equipment.manage'],
        'admin' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name => $label) {
            Role::findOrCreate($name, 'mes');
        }

        foreach (self::GRANTS as $role => $permissions) {
            if ($permissions === []) {
                continue;
            }
            Role::findByName($role, 'mes')->syncPermissions($permissions);
        }

        // 管理员：全权限
        Role::findByName('admin', 'mes')
            ->syncPermissions(array_keys(PermissionSeeder::PERMISSIONS));
    }
}
