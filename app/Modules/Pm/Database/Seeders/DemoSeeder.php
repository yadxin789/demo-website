<?php

namespace App\Modules\Pm\Database\Seeders;

use App\Modules\Pm\States\Project as ProjectState;
use App\Modules\Pm\States\Task as TaskState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 演示数据：4 个项目 / 20 个任务，覆盖进行中、已完成、暂停、待审核等状态，
 * 让客户登录后立刻看到"有内容"的甘特、任务列表、项目详情，而不是空表。
 *
 * 用 DB 门面直接写表（绕过 fillable/cast），status 存的是状态类的完整类名——
 * 这是原项目 model-states 的存储约定，Seeder 必须保持一致。
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $conn = DB::connection('pm');

        if ($conn->table('projects')->count() > 0) {
            $this->command?->warn('projects 已有数据，跳过 DemoSeeder');

            return;
        }

        $creator = $conn->table('users')->where('username', 'pm_manager_1')->first()
            ?? $conn->table('users')->where('username', 'admin')->first();
        $admin = $conn->table('users')->where('username', 'admin')->first();
        $now = now();

        $departments = $conn->table('departments')->where('type', 'project')->get();
        $typeIds = $conn->table('task_types')->pluck('id')->all();

        $projects = [
            [
                'name' => '智慧园区管理平台 V2.1',
                'code' => 'PRJ-2026-001',
                'version' => '2.1',
                'product_name' => '智慧园区管理平台',
                'product_model' => 'PARK-2100',
                'start' => $now->copy()->subDays(45)->toDateString(),
                'end' => $now->copy()->addDays(25)->toDateString(),
                'status' => ProjectState\InProgress::class,
                'progress' => 62,
            ],
            [
                'name' => '移动端巡检 APP 重构',
                'code' => 'PRJ-2026-002',
                'version' => '1.0',
                'product_name' => '巡检 APP',
                'product_model' => 'INSPECT-M',
                'start' => $now->copy()->subDays(30)->toDateString(),
                'end' => $now->copy()->addDays(40)->toDateString(),
                'status' => ProjectState\InProgress::class,
                'progress' => 38,
            ],
            [
                'name' => '客户数据中台一期',
                'code' => 'PRJ-2026-003',
                'version' => '1.0',
                'product_name' => '客户数据中台',
                'product_model' => 'CDP-100',
                'start' => $now->copy()->subDays(120)->toDateString(),
                'end' => $now->copy()->subDays(10)->toDateString(),
                'status' => ProjectState\Completed::class,
                'progress' => 100,
            ],
            [
                'name' => '智能仓储 WMS 接口联调',
                'code' => 'PRJ-2026-004',
                'version' => '1.2',
                'product_name' => '仓储管理系统',
                'product_model' => 'WMS-1200',
                'start' => $now->copy()->subDays(60)->toDateString(),
                'end' => $now->copy()->subDays(5)->toDateString(),
                'status' => ProjectState\Suspended::class,
                'progress' => 25,
            ],
        ];

        $rows = [];
        foreach ($projects as $p) {
            $rows[] = [
                'name' => $p['name'],
                'code' => $p['code'],
                'version' => $p['version'],
                'product_name' => $p['product_name'],
                'product_model' => $p['product_model'],
                'creator_id' => $creator->id,
                'start_date' => $p['start'],
                'end_date' => $p['end'],
                'status' => $p['status'],
                'progress' => $p['progress'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $conn->table('projects')->insert($rows);

        $projectIds = $conn->table('projects')->pluck('id', 'code');

        // 任务模板：名称 + 状态 +（可选）执行/审核部门
        $taskPlans = [
            'PRJ-2026-001' => [
                ['需求评审与范围确认', TaskState\Completed::class],
                ['总体架构设计', TaskState\Completed::class],
                ['门禁模块开发', TaskState\InProgress::class],
                ['能耗看板开发', TaskState\InProgress::class],
                ['联调测试报告', TaskState\ForAudit::class],
                ['用户手册与培训材料', TaskState\Pending::class],
            ],
            'PRJ-2026-002' => [
                ['UI 交互稿定稿', TaskState\Completed::class],
                ['离线巡检能力开发', TaskState\InProgress::class],
                ['拍照取证与上传', TaskState\ForAudit::class],
                ['性能优化（首屏 1s）', TaskState\Pending::class],
                ['灰度发布方案', TaskState\Pending::class],
            ],
            'PRJ-2026-003' => [
                ['数据源接入（12 个系统）', TaskState\Completed::class],
                ['标签体系与清洗规则', TaskState\Completed::class],
                ['客户画像模型', TaskState\Completed::class],
                ['数据质量报告', TaskState\Completed::class],
                ['一期验收归档', TaskState\Completed::class],
            ],
            'PRJ-2026-004' => [
                ['WMS 接口协议梳理', TaskState\Completed::class],
                ['库存同步接口开发', TaskState\Overdue::class],
                ['波次拣货联调', TaskState\Pending::class],
                ['异常回滚机制', TaskState\Pending::class],
            ],
        ];

        $taskRows = [];
        $seq = 0;
        foreach ($taskPlans as $code => $items) {
            $pid = $projectIds[$code] ?? null;
            if (! $pid) {
                continue;
            }

            $base = DB::connection('pm')->table('projects')->where('id', $pid)->first();
            $start = \Illuminate\Support\Carbon::parse($base->start_date);
            $end = \Illuminate\Support\Carbon::parse($base->end_date);
            $span = max(1, (int) $start->diffInDays($end));

            foreach ($items as $i => $item) {
                $seq++;
                $dept = $departments[$i % max(1, $departments->count())] ?? null;
                $deptId = $dept->id ?? 1;

                // 执行人/审核人：优先按部门用户名匹配，缺省用 admin 兜底
                $executor = $dept
                    ? ($conn->table('users')->where('username', $dept->code.'_designer_1')->first()
                        ?? $conn->table('users')->where('username', $dept->code.'_clerk_1')->first())
                    : null;
                $auditor = $dept
                    ? $conn->table('users')->where('username', $dept->code.'_manager_1')->first()
                    : null;

                $tStart = $start->copy()->addDays((int) round($span * $i / max(1, count($items))));
                $tEnd = $tStart->copy()->addDays(7);

                $taskRows[] = [
                    'project_id' => $pid,
                    'department_id' => $deptId,
                    'name' => $item[0],
                    'executor_id' => $executor->id ?? $admin->id,
                    'auditor_id' => $auditor->id ?? $admin->id,
                    'start_date' => $tStart->toDateString(),
                    'end_date' => $tEnd->toDateString(),
                    'status' => $item[1],
                    'task_type_id' => $typeIds ? $typeIds[$i % count($typeIds)] : null,
                    'is_overdue' => $item[1] === TaskState\Overdue::class,
                    'extension_days' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($taskRows) {
            $conn->table('tasks')->insert($taskRows);
        }

        $this->command?->info(sprintf(
            'DemoSeeder: %d 个项目 / %d 个任务',
            count($rows),
            count($taskRows)
        ));
    }
}
