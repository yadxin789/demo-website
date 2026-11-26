<?php

namespace App\Modules\Erp\Database\Seeders;

use App\Modules\Erp\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * 演示专用管理员。
 *
 * 源项目的 system_admin 只有运维类权限（system.* / admin.* / ai.*），业务页一律 403，
 * 这是给真实客户做数据权限隔离的设计。演示站要"点进去能看完整流程"，
 * 因此单独建一个 admin 账号：同一角色 + 显式授予全部权限，密码仍统一 123456。
 */
class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => '演示管理员',
                'email' => 'demo@erp.local',
                'password' => '123456',
                'status' => 1,
            ]
        );

        $admin->syncRoles(['system_admin']);
        $admin->syncPermissions(Permission::where('guard_name', 'erp')->get());

        $this->command?->info("演示管理员 admin/123456 就绪，权限 " . Permission::where('guard_name', 'erp')->count() . " 项");
    }
}
