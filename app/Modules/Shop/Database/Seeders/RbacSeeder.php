<?php

namespace App\Modules\Shop\Database\Seeders;

use App\Modules\Shop\Models\Permission;
use App\Modules\Shop\Models\Role;
use App\Modules\Shop\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 权限与角色种子。
 *
 * 角色目录：
 * - super-admin 超级管理员：全部权限（admin 账户即此角色）
 * - manager    店长：全业务查看 + POS 行内改价
 * - cashier    收银员：仪表盘 + POS 收银
 * - sales      销售员：仪表盘 + 基础资料 + 销售
 * - warehouse  仓管员：仪表盘 + 基础资料 + 仓储
 * - purchase   采购员：仪表盘 + 基础资料 + 采购 + 仓储查看
 * - finance    财务：仪表盘 + 财务 + 报表
 * - auditor    审计员：仪表盘 + 报表 + 系统（审计日志只读）
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['dashboard.view', '仪表盘查看', 'dashboard', 'menu'],
            ['master.view', '基础资料查看', 'master', 'menu'],
            ['purchase.view', '采购管理查看', 'purchase', 'menu'],
            ['warehouse.view', '仓储管理查看', 'warehouse', 'menu'],
            ['sales.view', '销售管理查看', 'sales', 'menu'],
            ['pos.view', 'POS 收银查看', 'pos', 'menu'],
            ['pos.price.adjust', 'POS 行内改价', 'pos', 'button'],
            ['finance.view', '财务会计查看', 'finance', 'menu'],
            ['report.view', '报表中心查看', 'report', 'menu'],
            ['system.view', '系统设置查看', 'system', 'menu'],
            ['system.ai.manage', 'AI 设置与授权管理', 'system', 'button'],
            ['system.user.manage', '用户与权限管理', 'system', 'button'],
        ];

        $permissionIds = [];
        foreach ($permissions as [$code, $name, $module, $type]) {
            $permissionIds[$code] = Permission::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'module' => $module, 'type' => $type]
            )->id;
        }

        $roleCatalog = [
            'super-admin' => [
                'name' => '超级管理员',
                'description' => '拥有全部权限',
                'permissions' => array_keys($permissionIds),
            ],
            'manager' => [
                'name' => '店长',
                'description' => '门店全业务查看与 POS 行内改价',
                'permissions' => [
                    'dashboard.view', 'master.view', 'purchase.view', 'warehouse.view',
                    'sales.view', 'pos.view', 'pos.price.adjust', 'finance.view', 'report.view',
                ],
            ],
            'cashier' => [
                'name' => '收银员',
                'description' => 'POS 收银与行内改价',
                'permissions' => ['dashboard.view', 'pos.view', 'pos.price.adjust'],
            ],
            'sales' => [
                'name' => '销售员',
                'description' => '销售订单与客户资料查看',
                'permissions' => ['dashboard.view', 'master.view', 'sales.view'],
            ],
            'warehouse' => [
                'name' => '仓管员',
                'description' => '库存、出入库、盘点与序列号台账',
                'permissions' => ['dashboard.view', 'master.view', 'warehouse.view'],
            ],
            'purchase' => [
                'name' => '采购员',
                'description' => '采购订单、收货与退货',
                'permissions' => ['dashboard.view', 'master.view', 'purchase.view', 'warehouse.view'],
            ],
            'finance' => [
                'name' => '财务',
                'description' => '应收应付、对账、结账与报表',
                'permissions' => ['dashboard.view', 'finance.view', 'report.view'],
            ],
            'auditor' => [
                'name' => '审计员',
                'description' => '审计日志与报表只读',
                'permissions' => ['dashboard.view', 'report.view', 'system.view'],
            ],
        ];

        foreach ($roleCatalog as $code => $definition) {
            $role = Role::updateOrCreate(
                ['code' => $code],
                ['name' => $definition['name'], 'description' => $definition['description']]
            );

            $role->permissions()->sync(array_map(
                fn (string $permissionCode) => $permissionIds[$permissionCode],
                $definition['permissions']
            ));
        }

        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => '系统管理员',
                'email' => 'admin@admin.com',
                'phone' => null,
                'status' => 'active',
                'password' => Hash::make('123456'),
            ]
        );
        $admin->roles()->sync([Role::where('code', 'super-admin')->value('id')]);
    }
}
