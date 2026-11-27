<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * HR 五个角色（规划书 §5.1）。
 *
 * 角色对应岗位：管理员、人事经理、人事专员、部门主管、员工。
 */
class RoleSeeder extends Seeder
{
    /** 角色名 => 中文名（界面显示用） */
    public const ROLES = [
        'admin' => '系统管理员',
        'hr_manager' => '人事经理',
        'hr_specialist' => '人事专员',
        'dept_manager' => '部门主管',
        'employee' => '员工',
    ];

    /** 角色 => 权限（admin 单独给全量） */
    private const GRANTS = [
        'hr_manager' => [
            'org.view', 'org.manage', 'employee.view', 'attendance.view',
            'leave.view', 'leave.approve', 'payroll.view', 'payroll.manage',
            'performance.view', 'performance.manage', 'report.view',
        ],
        'hr_specialist' => [
            'org.view', 'employee.view', 'employee.manage',
            'attendance.view', 'attendance.manage', 'leave.view',
            'recruit.view', 'recruit.manage', 'import.run', 'report.view',
        ],
        'dept_manager' => [
            'org.view', 'employee.view', 'attendance.view',
            'leave.view', 'leave.approve', 'performance.view', 'performance.manage',
            'report.view',
        ],
        'employee' => [
            'leave.view', 'payroll.view',
        ],
        'admin' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name => $label) {
            Role::findOrCreate($name, 'hr');
        }

        foreach (self::GRANTS as $role => $permissions) {
            if ($permissions === []) {
                continue;
            }

            $model = Role::findByName($role, 'hr');
            $model->syncPermissions($permissions);
        }

        // admin 全量
        $admin = Role::findByName('admin', 'hr');
        $admin->syncPermissions(array_keys(PermissionSeeder::PERMISSIONS));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
