<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use App\Modules\Hr\Models\Certificate;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeSkill;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\ShiftAssignment;
use App\Modules\Hr\Models\Skill;
use App\Modules\Hr\Models\TrainingCourse;
use App\Modules\Hr\Models\TrainingRecord;
use App\Modules\Hr\Models\TrainingSession;
use Illuminate\Database\Seeder;

/**
 * 培训 / 技能 / 证书 / 排班 演示数据（M7 增补）。
 *
 * 幂等：按业务键（课程编号 / 场次号 / 技能编号 / 人+技能 / 人+日期）updateOrCreate。
 */
class TrainingSkillSeeder extends Seeder
{
    public function run(): void
    {
        $courses = $this->seedCourses();
        $this->seedSessions($courses);
        $skills = $this->seedSkills();
        $this->seedEmployeeSkills($skills);
        $this->seedCertificates();
        $this->seedRoster();
    }

    /** @return array<int, TrainingCourse> */
    private function seedCourses(): array
    {
        $defs = [
            ['TC-001', '安全生产三级教育', '安全', 8, '安环部'],
            ['TC-002', '注塑机操作与调机', '技能', 16, '设备部'],
            ['TC-003', '质量意识与 8D 方法', '技能', 6, '品质部'],
            ['TC-004', '班组长现场管理', '管理', 12, '人力资源部'],
            ['TC-005', '新员工入职引导', '通用', 4, '人力资源部'],
            ['TC-006', '消防与应急疏散演练', '合规', 3, '安环部'],
        ];

        $out = [];
        foreach ($defs as [$code, $title, $cat, $hours, $provider]) {
            $out[] = TrainingCourse::query()->updateOrCreate(
                ['code' => $code],
                ['title' => $title, 'category' => $cat, 'hours' => $hours, 'provider' => $provider, 'is_active' => true, 'description' => '演示数据：' . $title]
            );
        }

        return $out;
    }

    /** @param array<int, TrainingCourse> $courses */
    private function seedSessions(array $courses): void
    {
        $active = Employee::query()->where('status', 'active')->orderBy('employee_no')->limit(30)->get();

        $plan = [
            ['TS-2026-001', 0, -40, -40, '一号培训室', '王教官', 'done'],
            ['TS-2026-002', 1, -20, -19, '注塑车间', '设备部 李工', 'done'],
            ['TS-2026-003', 2, -7, -7, '二号培训室', '品质部 张主管', 'done'],
            ['TS-2026-004', 3, 3, 4, '一号培训室', '外聘讲师', 'planned'],
            ['TS-2026-005', 5, 10, 10, '厂区空地', '安环部', 'planned'],
        ];

        foreach ($plan as $i => [$no, $courseIdx, $startOffset, $endOffset, $loc, $trainer, $status]) {
            $session = TrainingSession::query()->updateOrCreate(
                ['session_no' => $no],
                [
                    'course_id' => $courses[$courseIdx]->id,
                    'start_date' => now()->addDays($startOffset)->toDateString(),
                    'end_date' => now()->addDays($endOffset)->toDateString(),
                    'location' => $loc,
                    'trainer' => $trainer,
                    'capacity' => 30,
                    'status' => $status,
                ]
            );

            // 已结束的期次：前 10 人报名，8 人通过 2 人补考；未开始的：前 12 人待定
            $slice = $active->slice($i * 3, 10);
            foreach ($slice as $k => $emp) {
                $result = $status === 'done'
                    ? ($k < 8 ? 'pass' : 'fail')
                    : 'pending';

                TrainingRecord::query()->updateOrCreate(
                    ['session_id' => $session->id, 'employee_id' => $emp->id],
                    [
                        'result' => $result,
                        'score' => $result === 'pending' ? null : ($result === 'pass' ? 82 + ($k % 12) : 55 + ($k % 8)),
                        'completed_at' => $result === 'pending' ? null : now()->addDays($endOffset),
                    ]
                );
            }
        }
    }

    /** @return array<int, Skill> */
    private function seedSkills(): array
    {
        $defs = [
            ['SK-001', '注塑机调机', '设备操作', 5],
            ['SK-002', '数控编程', '设备操作', 5],
            ['SK-003', '装配作业', '操作技能', 5],
            ['SK-004', '质量检验', '质量', 5],
            ['SK-005', '叉车驾驶', '特种作业', 3],
            ['SK-006', '电气维修', '设备操作', 5],
            ['SK-007', '班组管理', '管理', 5],
            ['SK-008', 'Excel 数据处理', '办公', 5],
        ];

        $out = [];
        foreach ($defs as [$code, $name, $cat, $max]) {
            $out[] = Skill::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $cat, 'max_level' => $max, 'description' => '演示数据']
            );
        }

        return $out;
    }

    /** @param array<int, Skill> $skills */
    private function seedEmployeeSkills(array $skills): void
    {
        $employees = Employee::query()->where('status', 'active')->orderBy('employee_no')->limit(40)->get();

        foreach ($employees as $i => $emp) {
            // 每人 2~4 项技能，等级按岗位随机但稳定（用 id 取模，避免每次跑不一样）
            $count = 2 + ($emp->id % 3);
            for ($k = 0; $k < $count; $k++) {
                $skill = $skills[($emp->id + $k * 3) % count($skills)];

                EmployeeSkill::query()->updateOrCreate(
                    ['employee_id' => $emp->id, 'skill_id' => $skill->id],
                    [
                        'level' => 1 + (($emp->id + $k) % 5),
                        'assessed_on' => now()->subMonths(6)->toDateString(),
                        'assessor' => '车间主任',
                    ]
                );
            }
        }
    }

    private function seedCertificates(): void
    {
        $employees = Employee::query()->where('status', 'active')->orderBy('employee_no')->limit(20)->get();

        $defs = [
            ['低压电工作业证', '应急管理局', -30],   // 已过期
            ['叉车司机证（N1）', '市场监管局', 15],  // 30 天内到期
            ['特种设备操作证', '市场监管局', 45],    // 60 天内到期
            ['ISO9001 内审员', '认证机构', 400],
            ['初级会计职称', '财政部', 900],
        ];

        foreach ($employees as $i => $emp) {
            $def = $defs[$i % count($defs)];
            $expire = now()->addDays($def[2] + ($i % 5) * 10);

            Certificate::query()->updateOrCreate(
                ['employee_id' => $emp->id, 'name' => $def[0]],
                [
                    'cert_no' => 'CERT-' . str_pad((string) ($emp->id + 1000), 6, '0', STR_PAD_LEFT),
                    'issuer' => $def[1],
                    'issued_on' => now()->subYears(3)->toDateString(),
                    'expire_on' => $expire->toDateString(),
                    'remark' => '演示数据',
                ]
            );
        }
    }

    private function seedRoster(): void
    {
        $employees = Employee::query()->where('status', 'active')->orderBy('employee_no')->limit(30)->get();
        $shifts = Shift::query()->orderBy('code')->get();
        if ($shifts->isEmpty()) {
            return;
        }

        $weekStart = now()->startOfWeek();

        foreach ($employees as $i => $emp) {
            for ($d = 0; $d < 5; $d++) {
                $date = $weekStart->copy()->addDays($d);

                // 每周轮换班次，模拟倒班；10% 的人本周请假一天
                $shift = $shifts[($i + $d) % $shifts->count()];
                $status = ($i % 10 === 0 && $d === 4) ? 'leave' : ($d <= 1 ? 'actual' : 'planned');

                ShiftAssignment::query()->updateOrCreate(
                    ['employee_id' => $emp->id, 'work_date' => $date->toDateString()],
                    ['shift_id' => $shift->id, 'status' => $status, 'remark' => '演示数据']
                );
            }
        }
    }
}
