<?php

declare(strict_types=1);

namespace App\Modules\Mes\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * MES 种子总编排。
 *
 * 顺序有依赖：权限 → 角色（要权限）→ 账号（要角色）→ 主数据 → 演示业务数据（要主数据与账号）。
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            OrgSeeder::class,
            MasterSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
