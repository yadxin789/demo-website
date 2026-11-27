<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 演示业务数据（H2/H3 域）：80 名员工、合同、异动、打卡、异常、加班、请假、余额。
 *
 * 人设（规划书 §6）：「演示企业」，综合集团，制造 / 贸易 / 服务三个板块。
 * 在职 68 / 试用 8 / 离职 4；姓名与 ERP 演示数据完全不重合（HC-M04）。
 * 可重复执行：先清业务表，再按业务编码重建（不做增量）。
 */
class DemoSeeder extends Seeder
{
    /** 92 个姓名（80 员工 + 12 备用），刻意避开 ERP 演示里常见的「张三/李四」 */
    private const NAMES = [
        '秦雅思', '柳含烟', '何淑敏', '薛峰', '温子澄', '范书宁', '康亦柔', '路遥岑',
        '苗雨桐', '柯予安', '滕语汐', '骆星霖', '卓沐阳', '储千帆', '倪清和', '强婉如',
        '车玉衡', '邢知微', '芦念慈', '桑榆晚', '贲若初', '蔺子昂', '越明轩', '习静姝',
        '桑田川', '仇文澜', '敖清野', '冷孤鸿', '卓然', '粟禾', '闻人朔', '蒲柳',
        '巩清源', '戚未晚', '关山月', '苗青禾', '宿雨眠', '瞿听澜', '党书白', '桑晓棠',
        '岑溪云', '窦雅南', '慕青禾', '解兰因', '隋寄风', '楼映白', '相凝心', '雍静宜',
        '蒲苇', '燕衔泥', '鲍子衿', '卢照邻', '柳三变', '白乐天', '苏子瞻', '辛幼安',
        '李清照', '晏小山', '秦少游', '周美成', '姜白石', '吴梦窗', '张玉田', '王碧山',
        '陈简斋', '吕圣求', '贺方回', '张于湖', '范石湖', '杨诚斋', '陆放翁', '刘后村',
        '黄山谷', '晁无咎', '叶梦得', '朱淑真', '严蕊', '唐婉', '管道昇', '魏夫人',
        '花蕊', '薛涛', '鱼玄机', '李季兰', '刘采春', '关盼盼', '杜秋娘', '韦庄',
        '温庭筠', '皇甫松', '牛希济', '李珣', '欧阳炯', '和凝', '孙光宪', '毛文锡',
    ];

    /** 编制：部门 => [岗位 => 人数]（合计 80） */
    private const ROSTER = [
        'D001' => ['P001' => 1],
        'D002' => ['P002' => 1, 'P009' => 1],
        'D003' => ['P003' => 1],
        'D004' => ['P004' => 1, 'P005' => 1, 'P006' => 3, 'P007' => 2],
        'D005' => ['P008' => 1, 'P009' => 3],
        'D006' => ['P010' => 1],
        'D007' => ['P011' => 1, 'P012' => 3, 'P013' => 2],
        'D008' => ['P014' => 1],
        'D009' => ['P015' => 1, 'P016' => 3],
        'D010' => ['P017' => 1, 'P018' => 4, 'P019' => 12],
        'D011' => ['P020' => 1, 'P021' => 4],
        'D012' => ['P022' => 1, 'P023' => 4],
        'D013' => ['P024' => 1],
        'D014' => ['P025' => 2, 'P026' => 4],
        'D015' => ['P027' => 1],
        'D016' => ['P028' => 1, 'P029' => 5, 'P030' => 3],
        'D017' => ['P031' => 1, 'P032' => 2],
        'D018' => ['P033' => 1],
        'D019' => ['P034' => 1, 'P035' => 4],
    ];

    private const EDU = ['本科', '大专', '硕士', '高中', '中专'];

    public function run(): void
    {
        $this->resetBusinessData();

        $deptIds = Department::query()->pluck('id', 'code');
        $posIds = Position::query()->pluck('id', 'code');

        // 展开成 80 个岗位位
        $slots = [];
        foreach (self::ROSTER as $deptCode => $positions) {
            foreach ($positions as $posCode => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $slots[] = [$deptCode, $posCode];
                }
            }
        }

        // 80 名员工：0–67 在职、68–75 试用、76–79 离职
        $employees = [];
        $now = now();
        $seq = 1;

        foreach ($slots as $idx => [$deptCode, $posCode]) {
            $name = self::NAMES[$idx] ?? ('员工' . $seq);
            $status = $idx >= 76 ? 'left' : ($idx >= 68 ? 'probation' : 'active');

            if ($status === 'probation') {
                $hire = $now->copy()->subDays(random_int(8, 58));
                $regular = null;
            } elseif ($status === 'left') {
                $hire = $now->copy()->subYears(random_int(2, 5))->subDays(random_int(0, 300));
                $regular = $hire->copy()->addMonths(3);
            } else {
                $hire = $now->copy()->subYears(random_int(1, 5))->subDays(random_int(0, 300));
                $regular = $hire->copy()->addMonths(3);
            }

            $employees[] = [
                'employee_no' => 'HR2026' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'gender' => $idx % 3 === 0 ? '女' : '男',
                'birthday' => $now->copy()->subYears(random_int(23, 45))->subDays(random_int(0, 364))->toDateString(),
                'id_card_masked' => '3201' . str_repeat('*', 10) . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
                'phone' => '138' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'email' => 'staff' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT) . '@huarui.demo',
                'department_id' => $deptIds[$deptCode] ?? null,
                'position_id' => $posIds[$posCode] ?? null,
                'hire_date' => $hire->toDateString(),
                'regular_date' => $regular?->toDateString(),
                'leave_date' => $status === 'left' ? $now->copy()->subDays(random_int(10, 150))->toDateString() : null,
                'status' => $status,
                'education' => self::EDU[$idx % count(self::EDU)],
                'remark' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $seq++;
        }

        DB::connection('hr')->table('employees')->insert($employees);

        $emps = Employee::query()->orderBy('employee_no')->get();

        // 部门负责人：各部门首位员工
        foreach ($emps as $e) {
            $dept = Department::query()->find($e->department_id);
            if ($dept && $dept->manager_id === null) {
                $dept->manager_id = $e->id;
                $dept->save();
            }
        }

        // 合同：每人一份；6 名在职员工合同 30 天内到期（演示提醒）
        $contracts = [];
        $cseq = 1;
        $expiringTargets = [3, 7, 11, 15, 22, 29];   // 员工序号（1 起）
        foreach ($emps as $i => $e) {
            $start = $e->hire_date;
            $end = $start->copy()->addYears(3);

            if (in_array($i + 1, $expiringTargets, true) && $e->status === 'active') {
                $end = now()->copy()->addDays(random_int(6, 28));
                $start = $end->copy()->subYears(3);
            }

            $contracts[] = [
                'employee_id' => $e->id,
                'contract_no' => 'CT-2026-' . str_pad((string) $cseq, 4, '0', STR_PAD_LEFT),
                'type' => $cseq % 5 === 0 ? 'open' : 'fixed',
                'start_date' => $start->toDateString(),
                'end_date' => $cseq % 5 === 0 ? null : $end->toDateString(),
                'sign_date' => $start->copy()->subDays(1)->toDateString(),
                'status' => $e->status === 'left' ? 'expired' : 'active',
                'remark' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $cseq++;
        }

        DB::connection('hr')->table('employee_contracts')->insert($contracts);

        // 异动：入职 + 转正 + 少量调岗
        $moves = [];
        foreach ($emps as $e) {
            $deptName = Department::query()->find($e->department_id)?->name;
            $posName = Position::query()->find($e->position_id)?->name;

            $moves[] = [
                'employee_id' => $e->id,
                'type' => 'hire',
                'effective_date' => $e->hire_date->toDateString(),
                'from_dept' => null,
                'to_dept' => $deptName,
                'from_position' => null,
                'to_position' => $posName,
                'operator_id' => null,
                'remark' => '入职办理',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($e->regular_date) {
                $moves[] = [
                    'employee_id' => $e->id,
                    'type' => 'regular',
                    'effective_date' => $e->regular_date->toDateString(),
                    'from_dept' => $deptName,
                    'to_dept' => $deptName,
                    'from_position' => $posName,
                    'to_position' => $posName,
                    'operator_id' => null,
                    'remark' => '试用期考核通过，转正',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($e->status === 'left') {
                $moves[] = [
                    'employee_id' => $e->id,
                    'type' => 'leave',
                    'effective_date' => (string) $e->leave_date?->toDateString(),
                    'from_dept' => $deptName,
                    'to_dept' => null,
                    'from_position' => $posName,
                    'to_position' => null,
                    'operator_id' => null,
                    'remark' => '离职交接完成',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // 几个调岗，演示异动流水有故事
        $transferTargets = [5, 18, 33, 46, 61];
        foreach ($transferTargets as $n) {
            $e = $emps[$n - 1] ?? null;
            if (! $e || $e->status === 'left') {
                continue;
            }
            $moves[] = [
                'employee_id' => $e->id,
                'type' => 'transfer',
                'effective_date' => now()->copy()->subDays(random_int(20, 120))->toDateString(),
                'from_dept' => '品质部',
                'to_dept' => Department::query()->find($e->department_id)?->name,
                'from_position' => '质检员',
                'to_position' => Position::query()->find($e->position_id)?->name,
                'operator_id' => null,
                'remark' => '内部调配，业务需要',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($moves, 200) as $chunk) {
            DB::connection('hr')->table('employee_movements')->insert($chunk);
        }

        $this->command?->info('员工 ' . count($emps) . ' 人 / 合同 ' . count($contracts) . ' 份 / 异动 ' . count($moves) . ' 条');
    }

    /** 清空 HR 业务表（按依赖顺序）；权限/账号/主数据保留 */
    public function resetBusinessData(): void
    {
        foreach ([
            'performance_reviews', 'performance_indicators', 'performance_cycles',
            'interviews', 'candidates', 'requisitions',
            'payroll_items', 'payroll_runs',
            'leave_balances', 'leaves',
            'overtimes', 'attendance_exceptions', 'attendance_records',
            'employee_movements', 'employee_contracts', 'employees',
        ] as $table) {
            DB::connection('hr')->table($table)->delete();
        }
    }
}
