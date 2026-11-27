<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use App\Modules\Hr\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * HR 五个演示账号（规划书 §5.2）。
 *
 * HC-13：密码 123456 只允许出现在 Seeder 里。
 */
class UserSeeder extends Seeder
{
    /** email => [姓名, 工号, 角色, 角色中文]（工号必须与 DemoSeeder 里同名员工的工号一致，工资条按工号匹配） */
    private const ACCOUNTS = [
        'admin@hr.demo' => ['何淑敏', 'HR2026003', 'admin', '系统管理员'],
        'manager@hr.demo' => ['秦雅思', 'HR2026001', 'hr_manager', '人事经理'],
        'specialist@hr.demo' => ['柳含烟', 'HR2026002', 'hr_specialist', '人事专员'],
        'dept@hr.demo' => ['薛峰', 'HR2026004', 'dept_manager', '部门主管'],
        'staff@hr.demo' => ['温子澄', 'HR2026005', 'employee', '员工'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ACCOUNTS as $email => [$name, $employeeNo, $role, $roleLabel]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->employee_no = $employeeNo;
            $user->role_code = $role;
            $user->password = '123456';   // 模型 casts 里 hashed，自动 bcrypt
            $user->status = 'active';
            $user->save();

            $user->syncRoles([$role]);
        }
    }
}
