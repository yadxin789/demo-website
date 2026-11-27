<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Headcount;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\SalaryStructure;
use App\Modules\Hr\Models\Shift;
use Illuminate\Database\Seeder;

/**
 * 组织主数据（H1 域）：部门树、岗位、编制、班次、岗位薪资结构。
 *
 * HC-M02：部门 D001–D0xx；岗位 P001–P0xx。
 * HC-M04：公司名「演示企业」，人名与 ERP「演示精密电子」完全不同。
 * 可重复执行：全部 updateOrCreate，按业务编码定位。
 */
class MasterSeeder extends Seeder
{
    /** 部门：编码 => [名称, 上级编码, 层级] */
    private const DEPARTMENTS = [
        'D001' => ['集团总部', null, 1],
        'D002' => ['集团办公室', 'D001', 2],
        'D003' => ['人力行政中心', 'D001', 2],
        'D004' => ['人力资源部', 'D003', 3],
        'D005' => ['行政部', 'D003', 3],
        'D006' => ['财务中心', 'D001', 2],
        'D007' => ['财务部', 'D006', 3],
        'D008' => ['运营中心', 'D001', 2],
        'D009' => ['采购部', 'D008', 3],
        'D010' => ['生产部', 'D008', 3],
        'D011' => ['品质部', 'D008', 3],
        'D012' => ['仓储部', 'D008', 3],
        'D013' => ['销售中心', 'D001', 2],
        'D014' => ['销售部', 'D013', 3],
        'D015' => ['研发中心', 'D001', 2],
        'D016' => ['研发部', 'D015', 3],
        'D017' => ['IT 部', 'D001', 2],
        'D018' => ['法务部', 'D001', 2],
        'D019' => ['客服部', 'D001', 2],
    ];

    /** 岗位：编码 => [名称, 部门编码, 职级] */
    private const POSITIONS = [
        'P001' => ['集团总经理', 'D001', 'M4'],
        'P002' => ['集团办主任', 'D002', 'M3'],
        'P003' => ['人力行政总监', 'D003', 'M3'],
        'P004' => ['人力资源经理', 'D004', 'M2'],
        'P005' => ['招聘主管', 'D004', 'M1'],
        'P006' => ['人事专员', 'D004', 'P3'],
        'P007' => ['薪酬专员', 'D004', 'P3'],
        'P008' => ['行政主管', 'D005', 'M1'],
        'P009' => ['行政专员', 'D005', 'P2'],
        'P010' => ['财务总监', 'D006', 'M3'],
        'P011' => ['会计主管', 'D007', 'M1'],
        'P012' => ['会计', 'D007', 'P3'],
        'P013' => ['出纳', 'D007', 'P2'],
        'P014' => ['运营总监', 'D008', 'M3'],
        'P015' => ['采购主管', 'D009', 'M1'],
        'P016' => ['采购专员', 'D009', 'P3'],
        'P017' => ['生产经理', 'D010', 'M2'],
        'P018' => ['产线班长', 'D010', 'M1'],
        'P019' => ['操作员', 'D010', 'P1'],
        'P020' => ['品质主管', 'D011', 'M1'],
        'P021' => ['质检员', 'D011', 'P2'],
        'P022' => ['仓储主管', 'D012', 'M1'],
        'P023' => ['仓管员', 'D012', 'P1'],
        'P024' => ['销售总监', 'D013', 'M3'],
        'P025' => ['销售经理', 'D014', 'M2'],
        'P026' => ['销售专员', 'D014', 'P3'],
        'P027' => ['研发总监', 'D015', 'M3'],
        'P028' => ['研发经理', 'D016', 'M2'],
        'P029' => ['软件工程师', 'D016', 'P4'],
        'P030' => ['硬件工程师', 'D016', 'P4'],
        'P031' => ['IT 主管', 'D017', 'M1'],
        'P032' => ['运维工程师', 'D017', 'P3'],
        'P033' => ['法务主管', 'D018', 'M1'],
        'P034' => ['客服主管', 'D019', 'M1'],
        'P035' => ['客服专员', 'D019', 'P2'],
    ];

    /** 各岗位薪资（基本 / 岗位 / 绩效 / 补贴） */
    private const PAY = [
        'M4' => [9000, 6000, 4000, 1500],
        'M3' => [7000, 4000, 3000, 1200],
        'M2' => [6000, 3000, 2200, 1000],
        'M1' => [5000, 2200, 1600, 800],
        'P4' => [4800, 2000, 1400, 700],
        'P3' => [4000, 1600, 1100, 600],
        'P2' => [3500, 1200, 800, 500],
        'P1' => [3000, 900, 600, 400],
    ];

    /** 班次：编码 => [名称, 上班, 下班, 迟到容忍分钟, 跨天] */
    private const SHIFTS = [
        'S01' => ['标准班', '09:00', '18:00', 5, false],
        'S02' => ['早班', '07:00', '15:30', 5, false],
        'S03' => ['夜班', '21:00', '06:00', 10, true],
    ];

    public function run(): void
    {
        // 1. 部门（先建父级，再挂子级）
        $deptIds = [];
        foreach (self::DEPARTMENTS as $code => [$name, $parentCode, $level]) {
            $dept = Department::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'parent_id' => $parentCode ? ($deptIds[$parentCode] ?? null) : null,
                    'level' => $level,
                    'status' => 'active',
                    'sort' => count($deptIds) + 1,
                ]
            );
            $deptIds[$code] = $dept->id;
        }

        // 2. 岗位
        $positionIds = [];
        foreach (self::POSITIONS as $code => [$name, $deptCode, $grade]) {
            $pos = Position::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'department_id' => $deptIds[$deptCode] ?? null,
                    'grade' => $grade,
                    'status' => 'active',
                ]
            );
            $positionIds[$code] = $pos->id;
        }

        // 3. 薪资结构（按岗位职级套档）
        foreach (self::POSITIONS as $code => [$name, $deptCode, $grade]) {
            [$base, $post, $perf, $allow] = self::PAY[$grade] ?? self::PAY['P1'];
            SalaryStructure::query()->updateOrCreate(
                ['position_id' => $positionIds[$code]],
                [
                    'base' => $base,
                    'post' => $post,
                    'performance' => $perf,
                    'allowance' => $allow,
                    'effective_date' => '2026-01-01',
                ]
            );
        }

        // 4. 班次
        foreach (self::SHIFTS as $code => [$name, $start, $end, $tolerance, $cross]) {
            Shift::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'work_start' => $start,
                    'work_end' => $end,
                    'late_tolerance_min' => $tolerance,
                    'cross_day' => $cross,
                    'status' => 'active',
                ]
            );
        }

        // 5. 编制：按部门在职目标（DemoSeeder 会回填 filled）
        $plans = [
            'D001' => [['P001', 1]],
            'D002' => [['P002', 1], ['P009', 1]],
            'D003' => [['P003', 1]],
            'D004' => [['P004', 1], ['P005', 1], ['P006', 4], ['P007', 2]],
            'D005' => [['P008', 1], ['P009', 3]],
            'D006' => [['P010', 1]],
            'D007' => [['P011', 1], ['P012', 3], ['P013', 2]],
            'D008' => [['P014', 1]],
            'D009' => [['P015', 1], ['P016', 3]],
            'D010' => [['P017', 1], ['P018', 4], ['P019', 18]],
            'D011' => [['P020', 1], ['P021', 6]],
            'D012' => [['P022', 1], ['P023', 6]],
            'D013' => [['P024', 1]],
            'D014' => [['P025', 2], ['P026', 6]],
            'D015' => [['P027', 1]],
            'D016' => [['P028', 1], ['P029', 6], ['P030', 4]],
            'D017' => [['P031', 1], ['P032', 2]],
            'D018' => [['P033', 1]],
            'D019' => [['P034', 1], ['P035', 4]],
        ];

        foreach ($plans as $deptCode => $rows) {
            foreach ($rows as [$posCode, $planned]) {
                Headcount::query()->updateOrCreate(
                    ['department_id' => $deptIds[$deptCode], 'position_id' => $positionIds[$posCode]],
                    ['planned' => $planned, 'filled' => 0]
                );
            }
        }

        $this->command?->info('组织主数据：部门 ' . count(self::DEPARTMENTS) . ' / 岗位 ' . count(self::POSITIONS) . ' / 班次 ' . count(self::SHIFTS));
    }
}
