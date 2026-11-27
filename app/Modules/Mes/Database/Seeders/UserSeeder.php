<?php

declare(strict_types=1);

namespace App\Modules\Mes\Database\Seeders;

use App\Modules\Mes\Models\User;
use Illuminate\Database\Seeder;

/**
 * MES 演示账号（规划书 §5.3）。
 *
 * HC-13：演示密码 123456 只允许出现在 Seeder，业务代码里不得出现。
 * 登录名：username（S9 起补列）或 email 均可（LoginController 支持「邮箱或用户名」）。
 */
class UserSeeder extends Seeder
{
    /** name(登录名) => [显示姓名, 邮箱, 工号, 班组, 角色] */
    private const USERS = [
        'admin' => ['admin', 'admin@mes.demo', 'JG-0001', '信息部', 'admin'],
        'plan' => ['计划员 周敏', 'plan@mes.demo', 'JG-0002', '计划部', 'planner'],
        'lead' => ['班组长 陈刚', 'lead@mes.demo', 'JG-0003', '一号车间 A 班', 'leader'],
        'qc' => ['质检员 李婷', 'qc@mes.demo', 'JG-0004', '品质部', 'inspector'],
        'eq' => ['设备工程师 王磊', 'eq@mes.demo', 'JG-0005', '设备部', 'equipment'],
    ];

    public function run(): void
    {
        foreach (self::USERS as $login => [$displayName, $email, $employeeNo, $team, $role]) {
            /** @var User $user */
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $login === 'admin' ? 'admin' : $displayName,
                    'username' => $login,   // S9：用户名登录（HC-42）
                    'employee_no' => $employeeNo,
                    'team' => $team,
                    'password' => '123456',
                    'status' => 'active',
                ]
            );

            $user->syncRoles([$role]);
        }
    }
}
