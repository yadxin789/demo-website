<?php

namespace App\Modules\Pm\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 演示数据覆盖（PM 侧）：补齐参考项目种子没覆盖的空表。
 *
 * 覆盖：通知 / 待办 / 项目文件与版本 / 部门资料文件 / 项目动态 / 项目模板 /
 *       默认成员 / 延期申请 / 知识库 / 项目留言 / AI 会话 / 管理员视角的任务。
 * 全部为虚拟演示数据；幂等：先判重再插入，可重复执行。
 */
class DemoCoverageSeeder extends Seeder
{
    private string $conn = 'pm';

    private const TASK_PENDING = 'App\Modules\Pm\States\Task\Pending';
    private const TASK_IN_PROGRESS = 'App\Modules\Pm\States\Task\InProgress';
    private const TASK_FOR_AUDIT = 'App\Modules\Pm\States\Task\ForAudit';
    private const TASK_COMPLETED = 'App\Modules\Pm\States\Task\Completed';
    private const TASK_OVERDUE = 'App\Modules\Pm\States\Task\Overdue';

    public function run(): void
    {
        $this->seedTasks();
        $this->seedNotifications();
        $this->seedTodos();
        $this->seedProjectFiles();
        $this->seedDepartmentFiles();
        $this->seedProjectLogs();
        $this->seedTemplates();
        $this->seedExtensionAndMembers();
        $this->seedKnowledge();
        $this->seedMessages();
        $this->seedAi();
        $this->seedDeletedUser();
        $this->seedOverdueAndTrash();
    }

    /**
     * 逾期项目 + 已删除项目 + 审核人视角的完成/逾期任务，补齐
     * 「我的项目-逾期」「回收站」「我的审核任务-已完成/逾期」三处空态。
     */
    private function seedOverdueAndTrash(): void
    {
        $ts = $this->now();
        $adminId = (int) (DB::connection($this->conn)->table('users')->where('username', 'admin')->value('id') ?: 0);

        if (Schema::connection($this->conn)->hasTable('projects')
            && ! $this->has('projects', ['code' => 'PRJ-2026-005'])) {
            $this->insert('projects', [[
                'name' => '演示逾期项目：门店数字化改造',
                'code' => 'PRJ-2026-005',
                'version' => '1.0',
                'product_name' => '门店数字化平台',
                'product_model' => 'STORE-100',
                'creator_id' => $adminId,
                'start_date' => $this->day(-90),
                'end_date' => $this->day(-14),
                'status' => 'App\Modules\Pm\States\Project\Overdue',
                'progress' => 45,
                'paused_reason' => null,
                'paused_at' => null,
                'resumed_at' => null,
                'total_paused_days' => 0,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
        }

        if (Schema::connection($this->conn)->hasTable('projects')
            && ! $this->has('projects', ['code' => 'PRJ-2026-006'])) {
            $this->insert('projects', [[
                'name' => '演示已删除项目：渠道活动页改版',
                'code' => 'PRJ-2026-006',
                'version' => '1.0',
                'product_name' => '渠道活动页',
                'product_model' => 'CAMP-1',
                'creator_id' => $adminId,
                'start_date' => $this->day(-60),
                'end_date' => $this->day(-30),
                'status' => 'App\Modules\Pm\States\Project\Completed',
                'progress' => 100,
                'paused_reason' => null,
                'paused_at' => null,
                'resumed_at' => null,
                'total_paused_days' => 0,
                'created_at' => $this->ts(-60),
                'updated_at' => $ts,
                'deleted_at' => $this->ts(-5, '10:00:00'),
                'deleted_by' => $adminId,
            ]]);
        }

        // 审核人视角：已完成 / 逾期 各一条
        $audits = [
            ['演示任务：接口文档评审（已审核）', 1, 7, $adminId, -25, -15, self::TASK_COMPLETED],
            ['演示任务：上线方案评审（已逾期）', 2, 13, $adminId, -30, -8, self::TASK_OVERDUE],
        ];
        foreach ($audits as [$name, $projectId, $executor, $auditor, $start, $end, $status]) {
            if ($this->has('tasks', ['name' => $name])) {
                continue;
            }
            $this->insert('tasks', [[
                'project_id' => $projectId,
                'department_id' => 10,
                'name' => $name,
                'executor_id' => $executor,
                'auditor_id' => $auditor,
                'start_date' => $this->day($start),
                'end_date' => $this->day($end),
                'status' => $status,
                'task_type_id' => 1,
                'is_overdue' => $status === self::TASK_OVERDUE ? 1 : 0,
                'extension_days' => 0,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
        }
    }

    // ---------------------------------------------------------------- 工具

    private function insert(string $table, array $rows): void
    {
        if ($rows === [] || ! Schema::connection($this->conn)->hasTable($table)) {
            return;
        }
        $cols = Schema::connection($this->conn)->getColumnListing($table);
        $clean = [];
        foreach ($rows as $row) {
            $clean[] = array_intersect_key($row, array_flip($cols));
        }
        try {
            DB::connection($this->conn)->table($table)->insert($clean);
        } catch (\Illuminate\Database\QueryException $e) {
            echo "  [跳过] {$table}: " . mb_substr($e->getMessage(), 0, 150) . PHP_EOL;
        }
    }

    private function has(string $table, array $where): bool
    {
        if (! Schema::connection($this->conn)->hasTable($table)) {
            return false;
        }

        return DB::connection($this->conn)->table($table)->where($where)->exists();
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->format('Y-m-d');
    }

    private function ts(int $offset, string $time = '09:00:00'): string
    {
        return now()->addDays($offset)->format('Y-m-d') . ' ' . $time;
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    // ------------------------------------------------- 管理员视角的任务矩阵

    /**
     * admin(id=1) 是演示站默认账号，参考项目种子把任务都分给了别人，
     * 于是「我的任务 / 待执行 / 待审核」各状态页全空。这里按状态矩阵补任务。
     */
    private function seedTasks(): void
    {
        if (! Schema::connection($this->conn)->hasTable('tasks')) {
            return;
        }
        $ts = $this->now();
        $adminId = (int) (DB::connection($this->conn)->table('users')->where('username', 'admin')->value('id') ?: 0);
        if ($adminId === 0) {
            return;
        }

        // [任务名, 项目id, 执行人, 审核人, 起始偏移, 截止偏移, 状态]
        $rows = [
            ['演示任务：需求基线确认', 1, $adminId, 3, -10, 4, self::TASK_PENDING],
            ['演示任务：接口联调方案', 1, $adminId, 3, -8, 6, self::TASK_IN_PROGRESS],
            ['演示任务：联调问题回归', 2, $adminId, 5, -20, -6, self::TASK_OVERDUE],
            ['演示任务：上线检查清单', 3, $adminId, 3, -30, -2, self::TASK_COMPLETED],
            ['演示任务：架构评审（待我审核）', 1, 7, $adminId, -6, 3, self::TASK_FOR_AUDIT],
            ['演示任务：数据库设计（待我审核）', 2, 13, $adminId, -5, 5, self::TASK_FOR_AUDIT],
            ['演示任务：性能压测（我审核中）', 4, 17, $adminId, -9, 8, self::TASK_IN_PROGRESS],
        ];

        foreach ($rows as [$name, $projectId, $executor, $auditor, $start, $end, $status]) {
            if ($this->has('tasks', ['name' => $name])) {
                continue;
            }
            $this->insert('tasks', [[
                'project_id' => $projectId,
                'department_id' => 10,
                'name' => $name,
                'executor_id' => $executor,
                'auditor_id' => $auditor,
                'start_date' => $this->day($start),
                'end_date' => $this->day($end),
                'status' => $status,
                'task_type_id' => 1,
                'is_overdue' => $status === self::TASK_OVERDUE ? 1 : 0,
                'extension_days' => 0,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
        }
    }

    // ------------------------------------------------------------- 通知/待办

    private function seedNotifications(): void
    {
        if ($this->has('notifications', ['title' => '演示通知：本周项目周报已生成'])) {
            return;
        }
        $ts = $this->now();
        $adminId = (int) (DB::connection($this->conn)->table('users')->where('username', 'admin')->value('id') ?: 0);
        $rows = [
            [$adminId, 3, 'system', '演示通知：本周项目周报已生成', '本周 4 个在建项目的周报已汇总，可在项目详情查看。', 'project', 1, 0],
            [$adminId, 3, 'task', '演示通知：有 2 条任务待你审核', '「架构评审」「数据库设计」已提交，请及时审核。', 'task', 5, 0],
            [$adminId, 2, 'extension', '演示通知：延期申请待处理', '有 1 条任务延期申请等待审批。', 'task', 3, 0],
            [$adminId, 3, 'system', '演示通知：里程碑已达成', '「客户数据中台一期」已完成验收。', 'project', 3, 1],
        ];
        $data = [];
        foreach ($rows as [$uid, $sender, $type, $title, $content, $relType, $relId, $isRead]) {
            $data[] = [
                'user_id' => $uid,
                'sender_id' => $sender,
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'related_type' => $relType,
                'related_id' => $relId,
                'is_read' => $isRead,
                'read_at' => $isRead ? $ts : null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }
        $this->insert('notifications', $data);
    }

    private function seedTodos(): void
    {
        if ($this->has('todos', ['title' => '演示待办：确认需求基线'])) {
            return;
        }
        $ts = $this->now();
        $adminId = (int) (DB::connection($this->conn)->table('users')->where('username', 'admin')->value('id') ?: 0);
        $rows = [
            ['task', '演示待办：确认需求基线', 'task', 1, 'pending'],
            ['task', '演示待办：审核架构评审文档', 'task', 5, 'pending'],
            ['project', '演示待办：整理里程碑汇报材料', 'project', 1, 'pending'],
            ['manual', '演示待办：更新部门资料文件', 'project', 2, 'completed'],
        ];
        $data = [];
        foreach ($rows as [$type, $title, $relType, $relId, $status]) {
            $data[] = [
                'user_id' => $adminId,
                'type' => $type,
                'title' => $title,
                'related_type' => $relType,
                'related_id' => $relId,
                'status' => $status,
                'completed_at' => $status === 'completed' ? $ts : null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }
        $this->insert('todos', $data);
    }

    // ------------------------------------------------------------ 文件与版本

    private function seedProjectFiles(): void
    {
        if ($this->has('files', ['name' => '演示文件：需求规格说明书V1.2.pdf'])) {
            return;
        }
        $ts = $this->now();
        $rows = [
            [1, '演示文件：需求规格说明书V1.2.pdf', 'requirements-v1.2.pdf', 512000, 'application/pdf', 'approved'],
            [1, '演示文件：架构设计说明书.docx', 'architecture.docx', 386000, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'approved'],
            [2, '演示文件：接口联调记录.xlsx', 'api-joint-test.xlsx', 128000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pending'],
            [3, '演示文件：验收报告.pdf', 'acceptance.pdf', 642000, 'application/pdf', 'approved'],
            [4, '演示文件：压测报告V1.pdf', 'pressure-test.pdf', 289000, 'application/pdf', 'rejected'],
        ];
        $data = [];
        foreach ($rows as $i => [$taskId, $name, $orig, $size, $mime, $status]) {
            $data[] = [
                'task_id' => $taskId,
                'uploader_id' => 1,
                'auditor_id' => 3,
                'name' => $name,
                'original_name' => $orig,
                'path' => 'demo/pm/' . $orig,
                'size' => $size,
                'mime_type' => $mime,
                'version' => 1,
                'status' => $status,
                'audit_time' => $status === 'pending' ? null : $this->ts(-2),
                'audit_reason' => $status === 'rejected' ? '请补充压测原始日志后重新提交' : null,
                'created_at' => $this->ts(-10 + $i),
                'updated_at' => $ts,
            ];
        }
        $this->insert('files', $data);

        // 文件版本：给第一条文件补 V1 / V2 历史
        if (! $this->has('file_versions', ['version' => 2])) {
            $this->insert('file_versions', [
                ['file_id' => 1, 'version' => 1, 'path' => 'demo/pm/requirements-v1.0.pdf', 'uploader_id' => 1, 'auditor_id' => 3, 'status' => 'approved', 'created_at' => $this->ts(-20), 'updated_at' => $ts, 'task_id' => 1],
                ['file_id' => 1, 'version' => 2, 'path' => 'demo/pm/requirements-v1.2.pdf', 'uploader_id' => 1, 'auditor_id' => 3, 'status' => 'approved', 'created_at' => $this->ts(-6), 'updated_at' => $ts, 'task_id' => 1],
            ]);
        }
    }

    private function seedDepartmentFiles(): void
    {
        if (DB::connection($this->conn)->table('department_files')->exists()) {
            return;
        }
        $ts = $this->now();
        $catalog = [
            ['template', '演示模板：项目立项书模板.docx', 'project-charter-template.docx'],
            ['example', '演示范例：需求规格说明书范例.pdf', 'requirements-example.pdf'],
            ['product_spec', '演示规格：智能终端产品规格书.pdf', 'product-spec.pdf'],
            ['component_spec', '演示规格：主控芯片规格书.pdf', 'chip-spec.pdf'],
            ['material', '演示物料：结构件物料清单.xlsx', 'bom-structural.xlsx'],
            ['safety', '演示安规：GB4943 安规要求.pdf', 'gb4943.pdf'],
            ['certification', '演示认证：CE 认证证书.pdf', 'ce-cert.pdf'],
            ['patent', '演示专利：外观专利受理通知书.pdf', 'patent-notice.pdf'],
        ];
        $data = [];
        foreach ($catalog as $i => [$category, $name, $orig]) {
            $data[] = [
                'department_id' => 10,
                'uploader_id' => 1,
                'name' => $name,
                'original_name' => $orig,
                'path' => 'demo/pm/dept/' . $orig,
                'size' => 120000 + $i * 40000,
                'mime_type' => str_contains($orig, '.pdf') ? 'application/pdf' : 'application/octet-stream',
                'category' => $category,
                'visibility' => 'all',
                'version' => 1,
                'created_at' => $this->ts(-15 + $i),
                'updated_at' => $ts,
            ];
        }
        $this->insert('department_files', $data);
    }

    private function seedProjectLogs(): void
    {
        if ($this->has('project_logs', ['content' => '创建项目并录入初始计划'])) {
            return;
        }
        $ts = $this->now();
        $this->insert('project_logs', [
            ['project_id' => 1, 'user_id' => 3, 'action' => 'create', 'content' => '创建项目并录入初始计划', 'created_at' => $this->ts(-45), 'updated_at' => $ts],
            ['project_id' => 1, 'user_id' => 1, 'action' => 'update', 'content' => '调整里程碑：架构评审提前一周', 'created_at' => $this->ts(-20), 'updated_at' => $ts],
            ['project_id' => 2, 'user_id' => 13, 'action' => 'update', 'content' => '提交接口联调记录', 'created_at' => $this->ts(-8), 'updated_at' => $ts],
            ['project_id' => 3, 'user_id' => 3, 'action' => 'complete', 'content' => '项目验收通过，进入结项归档', 'created_at' => $this->ts(-10), 'updated_at' => $ts],
        ]);
    }

    // -------------------------------------------------------- 模板 / 默认成员

    private function seedTemplates(): void
    {
        if ($this->has('project_templates', ['name' => '演示模板：标准研发项目'])) {
            return;
        }
        $ts = $this->now();
        $templates = [
            ['演示模板：标准研发项目', '从立项到结项的标准 6 阶段研发流程模板', 1],
            ['演示模板：小型迭代项目', '适用于 4 周内交付的小型迭代项目', 3],
        ];
        $data = [];
        foreach ($templates as [$name, $desc, $uid]) {
            $data[] = ['name' => $name, 'description' => $desc, 'user_id' => $uid, 'created_at' => $ts, 'updated_at' => $ts];
        }
        $this->insert('project_templates', $data);

        $tplIds = DB::connection($this->conn)->table('project_templates')
            ->whereIn('name', ['演示模板：标准研发项目', '演示模板：小型迭代项目'])
            ->pluck('id', 'name')->all();
        if ($tplIds === []) {
            return;
        }
        $tasks = [
            ['演示模板：标准研发项目', 1, 1, 3, 2, 5, 1],
            ['演示模板：标准研发项目', 2, 7, 5, 3, 7, 2],
            ['演示模板：标准研发项目', 3, 13, 11, 3, 10, 3],
            ['演示模板：标准研发项目', 4, 17, 15, 4, 5, 4],
            ['演示模板：小型迭代项目', 1, 1, 3, 2, 3, 1],
            ['演示模板：小型迭代项目', 2, 7, 5, 3, 5, 2],
        ];
        $rows = [];
        foreach ($tasks as [$tpl, $typeId, $executor, $auditor, $dept, $days, $sort]) {
            $rows[] = [
                'project_template_id' => $tplIds[$tpl],
                'task_type_id' => $typeId,
                'executor_id' => $executor,
                'auditor_id' => $auditor,
                'department_id' => $dept,
                'days' => $days,
                'sort_order' => $sort,
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }
        $this->insert('template_tasks', $rows);
    }

    private function seedExtensionAndMembers(): void
    {
        $ts = $this->now();

        if (! $this->has('extension_applications', ['reason' => '供应商样品到货延期，需顺延联调窗口'])) {
            $this->insert('extension_applications', [
                ['task_id' => 3, 'applicant_id' => 13, 'approver_id' => 1, 'days' => 5, 'reason' => '供应商样品到货延期，需顺延联调窗口', 'status' => 'pending', 'approved_at' => null, 'reject_reason' => null, 'created_at' => $this->ts(-2), 'updated_at' => $ts],
                ['task_id' => 2, 'applicant_id' => 7, 'approver_id' => 5, 'days' => 3, 'reason' => '等待第三方接口文档', 'status' => 'approved', 'approved_at' => $this->ts(-9), 'reject_reason' => null, 'created_at' => $this->ts(-12), 'updated_at' => $ts],
            ]);
        }

        if (! DB::connection($this->conn)->table('default_members')->exists()) {
            $this->insert('default_members', [
                ['department_id' => 2, 'task_template_id' => 1, 'executor_id' => 7, 'auditor_id' => 5, 'created_at' => $ts, 'updated_at' => $ts],
                ['department_id' => 3, 'task_template_id' => 2, 'executor_id' => 13, 'auditor_id' => 11, 'created_at' => $ts, 'updated_at' => $ts],
                ['department_id' => 4, 'task_template_id' => 3, 'executor_id' => 17, 'auditor_id' => 15, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }
    }

    // -------------------------------------------------------------- 知识库

    private function seedKnowledge(): void
    {
        $ts = $this->now();

        if (DB::connection($this->conn)->table('knowledge_categories')->count() === 0) {
            $this->insert('knowledge_categories', [
                ['pid' => 0, 'sort' => 1, 'title' => '研发规范', 'desc' => '研发流程与文档规范', 'status' => 1, 'created_at' => $ts, 'updated_at' => $ts],
                ['pid' => 0, 'sort' => 2, 'title' => '项目管理', 'desc' => '排期、里程碑与风险管理', 'status' => 1, 'created_at' => $ts, 'updated_at' => $ts],
                ['pid' => 0, 'sort' => 3, 'title' => '工具与模板', 'desc' => '常用模板与工具说明', 'status' => 1, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        $cateIds = DB::connection($this->conn)->table('knowledge_categories')->pluck('id', 'title')->all();

        if (DB::connection($this->conn)->table('knowledges')->count() === 0) {
            $rows = [
                ['演示知识：立项书怎么写', $cateIds['研发规范'] ?? 1, '立项书结构与常见问题', 1, 1],
                ['演示知识：需求变更流程', $cateIds['研发规范'] ?? 1, '变更申请、评审与基线更新', 1, 2],
                ['演示知识：周报与里程碑管理', $cateIds['项目管理'] ?? 2, '周报模板与里程碑推进方法', 1, 3],
                ['演示知识：常用文档模板清单', $cateIds['工具与模板'] ?? 3, '项目常用模板一览', 0, 4],
            ];
            $data = [];
            foreach ($rows as [$title, $cate, $desc, $share, $sort]) {
                $data[] = [
                    'admin_id' => 1,
                    'cate_id' => $cate,
                    'title' => $title,
                    'desc' => $desc,
                    'is_share' => $share,
                    'read' => 12,
                    'sort' => $sort,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('knowledges', $data);
        }

        if (DB::connection($this->conn)->table('knowledge_docs')->count() === 0) {
            $kIds = DB::connection($this->conn)->table('knowledges')->pluck('id', 'title')->all();
            $docs = [
                ['演示知识：立项书怎么写', '立项书结构说明', "## 立项书结构\n\n1. 背景与目标\n2. 范围与交付物\n3. 里程碑与资源\n"],
                ['演示知识：立项书怎么写', '立项书模板下载', "模板见「工具与模板」分类。"],
                ['演示知识：需求变更流程', '变更流程说明', "## 变更流程\n\n提出 → 评估 → 评审 → 基线更新 → 通知相关方。"],
                ['演示知识：周报与里程碑管理', '周报模板', "## 周报模板\n\n- 本周进展\n- 风险与阻塞\n- 下周计划\n"],
            ];
            $data = [];
            foreach ($docs as $i => [$kTitle, $docTitle, $md]) {
                if (! isset($kIds[$kTitle])) {
                    continue;
                }
                $data[] = [
                    'knowledge_id' => $kIds[$kTitle],
                    'pid' => 0,
                    'type' => 'markdown',
                    'title' => $docTitle,
                    'md_content' => $md,
                    'content' => strip_tags($md),
                    'link' => null,
                    'file_ids' => null,
                    'read' => 5 + $i,
                    'sort' => $i + 1,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('knowledge_docs', $data);
        }
    }

    // ------------------------------------------------------------ 项目留言

    private function seedMessages(): void
    {
        if ($this->has('messages', ['content' => '演示留言：接口联调已排期到本周四'])) {
            return;
        }
        $ts = $this->now();
        $this->insert('messages', [
            ['project_id' => 1, 'user_id' => 1, 'receiver_id' => null, 'is_private' => 0, 'content' => '演示留言：接口联调已排期到本周四', 'created_at' => $this->ts(-3, '10:00:00'), 'updated_at' => $ts],
            ['project_id' => 1, 'user_id' => 7, 'receiver_id' => null, 'is_private' => 0, 'content' => '演示留言：架构文档已更新到 V1.2，请复核', 'created_at' => $this->ts(-2, '14:30:00'), 'updated_at' => $ts],
            ['project_id' => 2, 'user_id' => 13, 'receiver_id' => 1, 'is_private' => 1, 'content' => '演示留言：联调环境账号需要重置密码', 'created_at' => $this->ts(-1, '09:10:00'), 'updated_at' => $ts],
        ]);
    }

    // -------------------------------------------------------------- AI 会话

    private function seedAi(): void
    {
        if (Schema::connection($this->conn)->hasTable('ai_conversations')
            && $this->has('ai_conversations', ['title' => '演示会话：项目风险怎么排'])) {
            return;
        }
        $adminId = (int) (DB::connection($this->conn)->table('users')->where('username', 'admin')->value('id') ?: 0);
        if ($adminId === 0) {
            return;
        }
        $conversations = [
            ['演示会话：项目风险怎么排', '这个项目最大的三个风险是什么？', '进度风险集中在联调窗口，质量风险在压测覆盖度，资源风险在测试人力。'],
            ['演示会话：周报怎么写', '帮我生成一份项目周报提纲。', '周报建议包含：本周进展、里程碑状态、风险与阻塞、下周计划、需协助事项。'],
        ];
        foreach ($conversations as [$title, $q, $a]) {
            $convId = (int) DB::connection($this->conn)->table('ai_conversations')->insertGetId([
                'user_id' => $adminId,
                'title' => $title,
                'skill_slug' => null,
                'message_count' => 2,
                'last_message_at' => $this->ts(-1, '16:00:00'),
                'created_at' => $this->ts(-1),
                'updated_at' => $this->ts(-1),
                'model_id' => null,
            ]);
            $this->insert('ai_messages', [
                ['conversation_id' => $convId, 'role' => 'user', 'content' => $q, 'meta' => null, 'created_at' => $this->ts(-1, '15:58:00'), 'updated_at' => $this->ts(-1, '15:58:00')],
                ['conversation_id' => $convId, 'role' => 'assistant', 'content' => $a, 'meta' => null, 'created_at' => $this->ts(-1, '16:00:00'), 'updated_at' => $this->ts(-1, '16:00:00')],
            ]);
        }
    }

    // --------------------------------------------- 已删除用户（回收站页面用）

    private function seedDeletedUser(): void
    {
        if ($this->has('users', ['username' => 'demo_leaver'])) {
            return;
        }
        $ts = $this->now();
        $this->insert('users', [[
            'username' => 'demo_leaver',
            'password' => bcrypt('demo123456'),
            'name' => '演示离职账号',
            'avatar' => null,
            'department_id' => 10,
            'role_id' => 5,
            'is_active' => 0,
            'email' => 'demo_leaver@pm.demo',
            'created_at' => $this->ts(-60),
            'updated_at' => $ts,
            'deleted_at' => $this->ts(-5, '11:00:00'),
            'deleted_by' => 1,
        ]]);
    }
}
