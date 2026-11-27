<?php

declare(strict_types=1);

namespace App\Modules\Hr\Database\Seeders;

use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\Requisition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 招聘与绩效种子（H5）。
 *
 * 招聘：8 个需求（在招 5）、30 名候选人（各阶段分布）、20 场面试。
 * 绩效：2 个周期（2026Q3 已结束 / 2026Q4 进行中）、10 个指标、在职员工 × 5 指标评分。
 */
class RecruitPerformanceSeeder extends Seeder
{
    private const CANDIDATE_NAMES = [
        '花清和', '莫晚舟', '边知远', '隆砚秋', '扈清欢', '晏寻芳', '骆听白', '隋映雪',
        '汲云舒', '席泽川', '湛清音', '桑叶青', '解语花', '褚怀瑾', '樊见山', '聂南枝',
        '巫行云', '甘青鸾', '柯望舒', '霍听松', '乔半山', '单白露', '樊素月', '宗望川',
        '薄映川', '郎清逸', '毕云舟', '元安歌', '封临川', '滕素心',
    ];

    public function run(): void
    {
        foreach ([
            'performance_reviews', 'performance_indicators', 'performance_cycles',
            'interviews', 'candidates', 'requisitions',
        ] as $t) {
            DB::connection('hr')->table($t)->delete();
        }

        $positions = Position::query()->get()->keyBy('code');
        $deptsOfPos = Position::query()->pluck('department_id', 'id');

        // 8 个招聘需求
        $reqs = [
            ['P029', 3, 'high', 'open'],
            ['P030', 2, 'normal', 'open'],
            ['P026', 2, 'normal', 'open'],
            ['P021', 2, 'high', 'open'],
            ['P019', 4, 'high', 'open'],
            ['P012', 1, 'normal', 'closed'],
            ['P023', 2, 'normal', 'closed'],
            ['P035', 2, 'normal', 'closed'],
        ];

        $rows = [];
        $hired = [];
        foreach ($reqs as $i => [$posCode, $headcount, $urgency, $status]) {
            $pos = $positions[$posCode] ?? null;
            $rows[] = [
                'req_no' => 'REQ-2026-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'position_id' => $pos?->id,
                'department_id' => $pos?->department_id,
                'headcount' => $headcount,
                'hired' => $status === 'closed' ? $headcount : ($i < 2 ? 1 : 0),
                'urgency' => $urgency,
                'status' => $status,
                'requester_id' => null,
                'remark' => $urgency === 'high' ? '业务扩张，优先补人' : null,
                'raised_on' => now()->copy()->subDays(20 + $i * 6)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::connection('hr')->table('requisitions')->insert($rows);

        $reqModels = Requisition::query()->orderBy('req_no')->get();

        // 30 名候选人：stage 分布 12 筛选 / 9 面试 / 5 offer / 2 入职 / 2 淘汰
        $stages = array_merge(
            array_fill(0, 12, 'screening'),
            array_fill(0, 9, 'interview'),
            array_fill(0, 5, 'offer'),
            array_fill(0, 2, 'onboard'),
            array_fill(0, 2, 'reject')
        );

        $cands = [];
        foreach ($stages as $i => $stage) {
            $req = $reqModels[$i % $reqModels->count()];
            $name = self::CANDIDATE_NAMES[$i] ?? ('候选人' . ($i + 1));

            $cands[] = [
                'requisition_id' => $req->id,
                'name' => $name,
                'phone' => '139' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'email' => 'cand' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . '@mail.demo',
                'source' => ['内推', '猎头', 'BOSS', '校招'][$i % 4],
                'years' => 1 + ($i % 8),
                'resume_summary' => ['熟悉制造业流程，有同类岗位经验', '技术扎实，项目经验完整', '沟通表达好，稳定性强', '带过小团队，能扛指标'][$i % 4],
                'stage' => $stage,
                'remark' => null,
                'created_at' => now()->copy()->subDays(random_int(3, 40)),
                'updated_at' => now(),
            ];
        }

        DB::connection('hr')->table('candidates')->insert($cands);

        $candModels = Candidate::query()->orderBy('id')->get();

        // 20 场面试：候选人在 interview 及以后阶段的有面试记录
        $interviews = [];
        $round = 0;
        foreach ($candModels as $c) {
            if (! in_array($c->stage, ['interview', 'offer', 'onboard', 'reject'], true)) {
                continue;
            }

            $round++;
            $interviews[] = [
                'candidate_id' => $c->id,
                'round' => 1,
                'interviewer_id' => null,
                'score' => random_int(6, 9),
                'result' => $c->stage === 'reject' ? 'fail' : 'pass',
                'feedback' => $c->stage === 'reject' ? '经验与岗位要求偏差较大' : '表达清晰，专业基础扎实，建议进入下一轮',
                'interviewed_at' => now()->copy()->subDays(random_int(1, 20)),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array($c->stage, ['offer', 'onboard'], true) && $round % 2 === 0) {
                $interviews[] = [
                    'candidate_id' => $c->id,
                    'round' => 2,
                    'interviewer_id' => null,
                    'score' => random_int(7, 10),
                    'result' => 'pass',
                    'feedback' => '复面通过，薪资期望在预算内',
                    'interviewed_at' => now()->copy()->subDays(random_int(1, 8)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (count($interviews) >= 20) {
                break;
            }
        }

        DB::connection('hr')->table('interviews')->insert($interviews);

        // 绩效：2 周期
        $cycles = [
            ['2026 年第三季度绩效', '2026Q3', now()->copy()->subMonths(4)->startOfQuarter(), now()->copy()->subMonths(1)->endOfQuarter(), 'closed'],
            ['2026 年第四季度绩效', '2026Q4', now()->copy()->startOfQuarter(), now()->copy()->endOfQuarter(), 'in_progress'],
        ];

        $cycleRows = [];
        foreach ($cycles as [$name, $period, $start, $end, $status]) {
            $cycleRows[] = [
                'name' => $name,
                'period' => $period,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::connection('hr')->table('performance_cycles')->insert($cycleRows);

        $cycleModels = DB::connection('hr')->table('performance_cycles')->orderBy('id')->get();

        // 10 个指标（每个周期 5 条）
        $indicators = [
            ['工作质量', 30, '交付物一次通过率 ≥ 95%'],
            ['工作效率', 25, '重点任务按期完成率 ≥ 90%'],
            ['协作沟通', 20, '跨部门协作评价良好'],
            ['专业能力', 15, '岗位技能达标，能独立承担'],
            ['纪律出勤', 10, '无迟到早退，请假合规'],
        ];

        $indRows = [];
        foreach ($cycleModels as $cycle) {
            foreach ($indicators as [$name, $weight, $target]) {
                $indRows[] = [
                    'cycle_id' => $cycle->id,
                    'name' => $name,
                    'weight' => $weight,
                    'target' => $target,
                    'scope' => '通用',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::connection('hr')->table('performance_indicators')->insert($indRows);

        // 评分：当前周期 × 在职员工 × 5 指标
        $activeEmps = Employee::query()->where('status', 'active')->get(['id']);
        $currentCycle = $cycleModels[count($cycleModels) - 1];
        $indModels = DB::connection('hr')->table('performance_indicators')
            ->where('cycle_id', $currentCycle->id)
            ->orderBy('id')
            ->get();

        $reviews = [];
        foreach ($activeEmps as $e) {
            $selfBase = random_int(70, 92);
            foreach ($indModels as $k => $ind) {
                $self = min(100, max(50, $selfBase + random_int(-4, 4)));
                $mgr = min(100, max(50, $self + random_int(-3, 5)));
                $reviews[] = [
                    'cycle_id' => $currentCycle->id,
                    'employee_id' => $e->id,
                    'indicator_id' => $ind->id,
                    'self_score' => $self,
                    'manager_score' => $k === 0 ? $mgr : null,   // 第一个指标演示已评，其余待评
                    'final_score' => null,
                    'comment' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($reviews, 300) as $chunk) {
            DB::connection('hr')->table('performance_reviews')->insert($chunk);
        }

        $this->command?->info('招聘需求 8 / 候选人 ' . count($cands) . ' / 面试 ' . count($interviews) . ' / 绩效指标 ' . count($indRows) . ' / 评分行 ' . count($reviews));
    }
}
