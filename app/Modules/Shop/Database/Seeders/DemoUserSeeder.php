<?php

namespace App\Modules\Shop\Database\Seeders;

use App\Modules\Shop\Models\Role;
use App\Modules\Shop\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 演示用户：为每个业务角色各建一个账号，统一密码 123456，便于按角色试跑权限。
 *
 * super-admin 的演示账号即 admin（账号资料由 RbacSeeder 维护），此处不再重复创建。
 * 本种子可重复执行：按 username 更新资料与密码，并重置角色归属。
 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = '123456';

    private const DEMOS = [
        ['username' => 'manager', 'name' => '演示店长', 'role' => 'manager'],
        ['username' => 'cashier', 'name' => '演示收银员', 'role' => 'cashier'],
        ['username' => 'sales', 'name' => '演示销售员', 'role' => 'sales'],
        ['username' => 'warehouse', 'name' => '演示仓管员', 'role' => 'warehouse'],
        ['username' => 'purchase', 'name' => '演示采购员', 'role' => 'purchase'],
        ['username' => 'finance', 'name' => '演示会计', 'role' => 'finance'],
        ['username' => 'auditor', 'name' => '演示审计员', 'role' => 'auditor'],
    ];

    public function run(): void
    {
        foreach (self::DEMOS as $demo) {
            $role = Role::where('code', $demo['role'])->first();

            if (! $role) {
                continue; // 角色目录由 RbacSeeder 维护，缺失时跳过避免建出无权限账号
            }

            $user = User::updateOrCreate(
                ['username' => $demo['username']],
                [
                    'name' => $demo['name'],
                    'email' => $demo['username'] . '@demo.local',
                    'phone' => null,
                    'status' => 'active',
                    'password' => Hash::make(self::PASSWORD),
                ]
            );

            $user->roles()->sync([$role->id]);
        }
    }
}
