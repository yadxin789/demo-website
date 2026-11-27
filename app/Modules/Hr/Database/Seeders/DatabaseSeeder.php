<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * HR 种子总编排。
 *
 * 顺序有依赖：权限 → 角色（要权限）→ 账号（要角色）→ 主数据（部门/岗位/编制/班次/薪资结构）
 *            → 员工 → 考勤假期（要员工）→ 薪酬 → 招聘绩效。
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            MasterSeeder::class,
            DemoSeeder::class,
            AttendanceSeeder::class,
            LeaveSeeder::class,
            PayrollSeeder::class,
            RecruitPerformanceSeeder::class,
        ]);
    }
}
