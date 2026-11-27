<?php

declare(strict_types=1);

namespace App\Modules\Mes\Database\Seeders;

use App\Modules\Mes\Models\Department;
use App\Modules\Mes\Models\Position;
use App\Modules\Mes\Models\User;
use Illuminate\Database\Seeder;

/**
 * MES 组织架构种子（S12-M06）。
 *
 * 让「部门管理 / 岗位管理 / 账号管理」三个页面一进去就有数据可看、可改：
 * 两级部门树 + 5 个岗位 + 5 个演示账号挂靠。幂等，可重复跑。
 */
class OrgSeeder extends Seeder
{
    /** 厂级部门：[名称, 排序] */
    private const ROOTS = [
        ['生产中心', 10],
        ['品质部', 20],
        ['设备部', 30],
        ['计划部', 40],
        ['信息部', 50],
    ];

    /** 子部门：[上级名称, 名称, 排序] */
    private const CHILDREN = [
        ['生产中心', '一号车间 A 班', 11],
        ['生产中心', 'SMT 车间', 12],
    ];

    /** 岗位：[名称, 所属部门名称] */
    private const POSITIONS = [
        ['系统管理员', '信息部'],
        ['生产计划员', '计划部'],
        ['班组长', '一号车间 A 班'],
        ['质检员', '品质部'],
        ['设备工程师', '设备部'],
    ];

    /** 账号挂靠：[邮箱 => [部门名称, 岗位名称]] */
    private const ASSIGN = [
        'admin@mes.demo' => ['信息部', '系统管理员'],
        'plan@mes.demo' => ['计划部', '生产计划员'],
        'lead@mes.demo' => ['一号车间 A 班', '班组长'],
        'qc@mes.demo' => ['品质部', '质检员'],
        'eq@mes.demo' => ['设备部', '设备工程师'],
    ];

    public function run(): void
    {
        $byName = [];

        foreach (self::ROOTS as [$name, $sort]) {
            $byName[$name] = Department::query()->updateOrCreate(
                ['name' => $name, 'parent_id' => null],
                ['sort' => $sort, 'status' => 'active']
            );
        }

        foreach (self::CHILDREN as [$parent, $name, $sort]) {
            $byName[$name] = Department::query()->updateOrCreate(
                ['name' => $name, 'parent_id' => $byName[$parent]->id],
                ['sort' => $sort, 'status' => 'active']
            );
        }

        foreach (self::POSITIONS as [$name, $department]) {
            Position::query()->updateOrCreate(
                ['name' => $name],
                ['department_id' => $byName[$department]->id, 'status' => 'active']
            );
        }

        foreach (self::ASSIGN as $email => [$department, $position]) {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                continue;
            }

            $positionId = Position::query()->where('name', $position)->value('id');
            $user->update([
                'department_id' => $byName[$department]->id,
                'position_id' => $positionId,
                'team' => $department,
            ]);
        }
    }
}
