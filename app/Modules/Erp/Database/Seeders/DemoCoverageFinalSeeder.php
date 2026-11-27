<?php

declare(strict_types=1);

namespace App\Modules\Erp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 演示数据覆盖种子（第三批：设计图档 / 回收站 / 目录类主数据补充）。
 *
 * 目的：让「外观图档」「包装图档」「回收站」「商品品牌」「工艺路线」「电商平台」
 * 这几个列表页在全新部署（php artisan db:seed）后也都有内容可看。
 * 全部为虚拟演示数据，幂等：先判重再插入，可重复执行。
 */
class DemoCoverageFinalSeeder extends Seeder
{
    private string $conn = 'erp';

    public function run(): void
    {
        $this->seedDesignTaskTypes();
        $this->seedDesignDocuments();
        $this->seedTrashItems();
        $this->seedCatalogBooster();
        $this->seedAdminWorkspace();
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

    private function count(string $table): int
    {
        return Schema::connection($this->conn)->hasTable($table)
            ? DB::connection($this->conn)->table($table)->count()
            : 0;
    }

    private function has(string $table, array $where): bool
    {
        if (! Schema::connection($this->conn)->hasTable($table)) {
            return false;
        }

        return DB::connection($this->conn)->table($table)->where($where)->exists();
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->format('Y-m-d');
    }

    private function ts(int $offset, string $time = '09:00:00'): string
    {
        return now()->addDays($offset)->format('Y-m-d') . ' ' . $time;
    }

    // ------------------------------------------------- 设计图档的任务类型

    /**
     * 「外观图档」页按 任务类型名称 like %外观% 过滤，而既有 37 个任务类型里
     * 只有「包装设计图」命中；补一个「外观设计图」，两页才都有数据可看。
     */
    private function seedDesignTaskTypes(): void
    {
        if (! Schema::connection($this->conn)->hasTable('rnd_task_types')) {
            return;
        }
        if ($this->has('rnd_task_types', ['name' => '外观设计图'])) {
            return;
        }

        $maxSort = (int) DB::connection($this->conn)->table('rnd_task_types')->max('sort_order');
        $this->insert('rnd_task_types', [[
            'name' => '外观设计图',
            'department_id' => 4,
            'description' => '外观效果图、CMF 规范与色板',
            'is_required' => 1,
            'sort_order' => $maxSort + 1,
            'code' => 'RND-TT-038',
            'status' => 1,
        ]]);
        echo '  任务类型新增：外观设计图' . PHP_EOL;
    }

    // --------------------------------------------------------- 设计图档数据

    private function seedDesignDocuments(): void
    {
        if (! Schema::connection($this->conn)->hasTable('rnd_project_tasks')
            || ! Schema::connection($this->conn)->hasTable('rnd_project_documents')) {
            return;
        }

        $ts = $this->now();
        $files = [
            ['外观效果图-一代.png', 'demo/design/appearance-1.png', 512000, 'image/png', '外观设计图', 'RP-DM-001'],
            ['CMF 色板与材质规范.pdf', 'demo/design/cmf-spec.pdf', 734000, 'application/pdf', '外观设计图', 'RP-DM-001'],
            ['外壳丝印稿.ai', 'demo/design/silk-print.ai', 286000, 'application/postscript', '外观设计图', 'RP-DM-002'],
            ['包装刀模图.dwg', 'demo/design/box-die.dwg', 418000, 'application/acad', '包装设计图', 'RP-DM-002'],
            ['彩盒印刷稿.pdf', 'demo/design/color-box.pdf', 962000, 'application/pdf', '包装设计图', 'RP-DM-001'],
        ];

        $typeIds = DB::connection($this->conn)->table('rnd_task_types')
            ->whereIn('name', ['外观设计图', '包装设计图'])
            ->pluck('id', 'name')->all();
        if ($typeIds === []) {
            return;
        }

        $projectIds = DB::connection($this->conn)->table('rnd_projects')
            ->whereIn('project_code', ['RP-DM-001', 'RP-DM-002'])
            ->pluck('id', 'project_code')->all();

        foreach ($files as $i => [$fileName, $path, $size, $mime, $typeName, $projectCode]) {
            if (! isset($typeIds[$typeName], $projectIds[$projectCode])) {
                continue;
            }

            // 1) 文件记录（media_files，幂等按 file_name 判重）
            if (! $this->has('media_files', ['file_name' => $fileName])) {
                $this->insert('media_files', [[
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'file_size' => $size,
                    'mime_type' => $mime,
                    'category' => 'design',
                    'uploader_id' => 8,
                    'created_at' => $this->ts(-15 + $i),
                ]]);
            }
            $fileId = (int) DB::connection($this->conn)->table('media_files')->where('file_name', $fileName)->value('id');

            // 2) 任务（每个项目 × 每个设计类型 1 条）
            $taskName = $typeName . '：' . pathinfo($fileName, PATHINFO_FILENAME);
            $task = DB::connection($this->conn)->table('rnd_project_tasks')
                ->where('project_id', $projectIds[$projectCode])
                ->where('task_type_id', $typeIds[$typeName])
                ->where('name', $taskName)->first();

            if ($task === null) {
                $taskId = (int) DB::connection($this->conn)->table('rnd_project_tasks')->insertGetId([
                    'project_id' => $projectIds[$projectCode],
                    'task_type_id' => $typeIds[$typeName],
                    'department_id' => 4,
                    'executor_id' => 8,
                    'auditor_id' => 2,
                    'start_date' => $this->day(-25 + $i),
                    'end_date' => $this->day(-5 + $i),
                    'status' => 'completed',
                    'is_overdue' => 0,
                    'extension_days' => 0,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                    'name' => $taskName,
                ]);
            } else {
                $taskId = (int) $task->id;
            }

            // 3) 交付文档（rnd_project_documents，幂等按 task_id + file_id 判重）
            if (! DB::connection($this->conn)->table('rnd_project_documents')
                ->where('task_id', $taskId)->where('file_id', $fileId)->exists()) {
                $this->insert('rnd_project_documents', [[
                    'project_id' => $projectIds[$projectCode],
                    'task_id' => $taskId,
                    'version_no' => 'V1',
                    'file_id' => $fileId,
                    'uploader_id' => 8,
                    'audit_status' => 'approved',
                    'auditor_id' => 2,
                    'audit_at' => $this->ts(-4),
                    'audit_remark' => '符合设计规范',
                    'created_at' => $this->ts(-15 + $i),
                    'is_baseline' => 1,
                    'baseline_at' => $this->ts(-4),
                    'superseded_by' => null,
                ]]);
            }
        }

        echo '  设计图档数据已补齐（外观图档 / 包装图档）' . PHP_EOL;
    }

    // ------------------------------------------------------------- 回收站

    private function seedTrashItems(): void
    {
        $ts = $this->now();

        // 1) 文件 / 图片：admin 删除的 3 条
        $trashFiles = [
            ['旧版产品主图（已弃用）.png', 'demo/trash/old-product.png', 86000, 'image/png'],
            ['过期检验标准V1.pdf', 'demo/trash/old-qc.pdf', 312000, 'application/pdf'],
            ['临时报价单.xlsx', 'demo/trash/temp-quote.xlsx', 48000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
        foreach ($trashFiles as $i => [$name, $path, $size, $mime]) {
            if ($this->has('media_files', ['file_name' => $name])) {
                continue;
            }
            $this->insert('media_files', [[
                'file_name' => $name,
                'file_path' => $path,
                'file_size' => $size,
                'mime_type' => $mime,
                'category' => 'trash_demo',
                'uploader_id' => 30,
                'created_at' => $this->ts(-9 + $i),
                'deleted_at' => $this->ts(-3 + $i, '15:20:00'),
                'deleted_by' => 30,
            ]]);
        }

        // 2) 聊天记录：admin 删除的 2 条
        if (Schema::connection($this->conn)->hasTable('system_chat_messages')
            && DB::connection($this->conn)->table('system_chat_rooms')->exists()) {
            $roomId = (int) DB::connection($this->conn)->table('system_chat_rooms')->min('id');
            $trashMsgs = ['这条留言已作废，请以公告为准。', '附件已更新到共享盘，旧版请忽略。'];
            foreach ($trashMsgs as $i => $content) {
                if (DB::connection($this->conn)->table('system_chat_messages')
                    ->where('sender_id', 30)->where('content', $content)->exists()) {
                    continue;
                }
                $this->insert('system_chat_messages', [[
                    'room_id' => $roomId,
                    'sender_id' => 30,
                    'msg_type' => 'text',
                    'content' => $content,
                    'file_id' => null,
                    'created_at' => $this->ts(-6 + $i, '10:05:00'),
                    'deleted_at' => $this->ts(-4 + $i, '10:30:00'),
                    'deleted_by' => 30,
                ]]);
            }
        }

        // 3) 协同文档：admin 删除的 1 条
        if (Schema::connection($this->conn)->hasTable('rnd_collab_documents')) {
            $projectId = (int) DB::connection($this->conn)->table('rnd_projects')->min('id');
            if ($projectId > 0 && ! $this->has('rnd_collab_documents', ['doc_key' => 'rnd-trash-1'])) {
                $this->insert('rnd_collab_documents', [[
                    'project_id' => $projectId,
                    'title' => '作废的评审记录（草稿）',
                    'content' => '演示文档：已被管理员移入回收站的草稿。',
                    'version' => 1,
                    'creator_id' => 30,
                    'updater_id' => 30,
                    'doc_type' => 'doc',
                    'extension' => 'md',
                    'storage_path' => 'demo/rnd/trash-draft.md',
                    'doc_key' => 'rnd-trash-1',
                    'size' => 2048,
                    'created_at' => $this->ts(-8),
                    'updated_at' => $this->ts(-8),
                    'deleted_at' => $this->ts(-3, '16:40:00'),
                    'deleted_by' => 30,
                ]]);
            }
        }

        echo '  回收站演示数据已补齐' . PHP_EOL;
    }

    // ------------------------------------------------- 目录类主数据补充

    private function seedCatalogBooster(): void
    {
        $ts = $this->now();

        // 商品品牌：补到 4 个
        if (Schema::connection($this->conn)->hasTable('product_brands')) {
            foreach ([['BR-03', '演示供应链品牌'], ['BR-04', '演示白牌']] as [$code, $name]) {
                if ($this->has('product_brands', ['code' => $code])) {
                    continue;
                }
                $this->insert('product_brands', [['code' => $code, 'name' => $name, 'logo' => null, 'created_at' => $ts, 'updated_at' => $ts]]);
            }
        }

        // 工艺路线：给成品与半成品各补一条
        if (Schema::connection($this->conn)->hasTable('routings')) {
            foreach ([['RT-SMT-01', 8, 'v1.0'], ['RT-ASSY-01', 9, 'v1.0']] as [$code, $materialId, $version]) {
                if ($this->has('routings', ['routing_code' => $code])) {
                    continue;
                }
                $this->insert('routings', [[
                    'routing_code' => $code,
                    'product_material_id' => $materialId,
                    'version' => $version,
                    'status' => 'approved',
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]]);
            }
        }

        // 电商平台：补两个渠道（离线演示，不填密钥）
        if (Schema::connection($this->conn)->hasTable('ecom_platforms')) {
            $secret = \Illuminate\Support\Facades\Crypt::encryptString('demo-secret');
            foreach ([['demo-mall', '演示商城（离线）'], ['demo-mini', '演示小程序商城（离线）']] as [$code, $name]) {
                if ($this->has('ecom_platforms', ['code' => $code])) {
                    continue;
                }
                $this->insert('ecom_platforms', [[
                    'code' => $code,
                    'name' => $name,
                    'app_key' => 'demo-app-key',
                    'app_secret' => $secret,
                    'shop_url' => null,
                    'status' => 1,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]]);
            }
        }

        echo '  目录类主数据已补充（品牌 / 工艺路线 / 电商平台）' . PHP_EOL;
    }

    // ------------------------------------------------- 演示账号（admin）自己的工作面

    /**
     * admin 是客户点开演示站最常用的账号，但它既不是项目参与人、也没有个人通知，
     * 导致「我的任务」「交付物上传台」「交付物审核台」「AI 对话」「待审 BOM」都是空的。
     * 这里给 admin 补上属于他自己的演示工作面。
     */
    private function seedAdminWorkspace(): void
    {
        $adminId = (int) (DB::connection($this->conn)->table('users')->where('username', 'admin')->value('id') ?? 0);
        if ($adminId === 0) {
            return;
        }
        $ts = $this->now();

        // 1) AI 对话：admin 名下 2 个会话
        if (Schema::connection($this->conn)->hasTable('ai_conversations')) {
            $providerId = (int) (DB::connection($this->conn)->table('ai_providers')->min('id') ?? 0) ?: null;
            $conversations = [
                ['演示会话：本月经营数据怎么看', '请帮我解读本月销售额与毛利率的变化趋势。', '本月销售额环比增长 12%，毛利率提升 1.8 个百分点，主要来自高毛利配件占比上升。'],
                ['演示会话：缺料工单怎么排', '有 3 张工单缺 MCU，应该先做哪张？', '建议优先排交期最近的 WO-DM-002，并把长交期物料提前 7 天锁定。'],
            ];
            foreach ($conversations as [$title, $question, $answer]) {
                if ($this->has('ai_conversations', ['user_id' => $adminId, 'title' => $title])) {
                    continue;
                }
                $convId = (int) DB::connection($this->conn)->table('ai_conversations')->insertGetId([
                    'title' => $title,
                    'user_id' => $adminId,
                    'provider_id' => $providerId,
                    'conversation_type' => 'chat',
                    'created_at' => $this->ts(-2),
                    'updated_at' => $this->ts(-2),
                    'skill_code' => null,
                    'message_count' => 2,
                    'last_message_at' => $this->ts(-2, '14:20:00'),
                ]);
                $this->insert('ai_messages', [
                    ['conversation_id' => $convId, 'role' => 'user', 'content' => $question, 'token_usage' => 0, 'created_at' => $this->ts(-2, '14:18:00'), 'updated_at' => $this->ts(-2, '14:18:00'), 'meta' => null],
                    ['conversation_id' => $convId, 'role' => 'assistant', 'content' => $answer, 'token_usage' => 0, 'created_at' => $this->ts(-2, '14:20:00'), 'updated_at' => $this->ts(-2, '14:20:00'), 'meta' => null],
                ]);
            }
        }

        // 2) 研发任务：admin 作为执行人 1 条、作为审核人 1 条（含一条待审交付物）
        if (Schema::connection($this->conn)->hasTable('rnd_project_tasks')) {
            $projectId = (int) (DB::connection($this->conn)->table('rnd_projects')->min('id') ?? 0);
            $typeId = (int) (DB::connection($this->conn)->table('rnd_task_types')->min('id') ?? 0);
            if ($projectId > 0 && $typeId > 0) {
                $rows = [
                    ['演示任务：项目周报汇总', $adminId, 2, 'pending', 0],
                    ['演示任务：结项资料归档审核', 8, $adminId, 'submitted', 1],
                ];
                foreach ($rows as [$name, $executor, $auditor, $status, $needDoc]) {
                    if ($this->has('rnd_project_tasks', ['name' => $name])) {
                        continue;
                    }
                    $taskId = (int) DB::connection($this->conn)->table('rnd_project_tasks')->insertGetId([
                        'project_id' => $projectId,
                        'task_type_id' => $typeId,
                        'department_id' => 1,
                        'executor_id' => $executor,
                        'auditor_id' => $auditor,
                        'start_date' => $this->day(-7),
                        'end_date' => $this->day(7),
                        'status' => $status,
                        'is_overdue' => 0,
                        'extension_days' => 0,
                        'created_at' => $ts,
                        'updated_at' => $ts,
                        'name' => $name,
                    ]);

                    if ($needDoc && Schema::connection($this->conn)->hasTable('rnd_project_documents')) {
                        $fileName = '结项资料归档清单.pdf';
                        if (! $this->has('media_files', ['file_name' => $fileName])) {
                            $this->insert('media_files', [[
                                'file_name' => $fileName,
                                'file_path' => 'demo/rnd/close-checklist.pdf',
                                'file_size' => 128000,
                                'mime_type' => 'application/pdf',
                                'category' => 'rnd',
                                'uploader_id' => 8,
                                'created_at' => $this->ts(-1),
                            ]]);
                        }
                        $fileId = (int) DB::connection($this->conn)->table('media_files')->where('file_name', $fileName)->value('id');
                        $this->insert('rnd_project_documents', [[
                            'project_id' => $projectId,
                            'task_id' => $taskId,
                            'version_no' => 'V1',
                            'file_id' => $fileId,
                            'uploader_id' => 8,
                            'audit_status' => 'pending',
                            'auditor_id' => $adminId,
                            'created_at' => $this->ts(-1),
                            'is_baseline' => 0,
                            'superseded_by' => null,
                        ]]);
                    }
                }
            }
        }

        // 3) 待审 BOM：再补一条 draft
        if (Schema::connection($this->conn)->hasTable('bom_headers')
            && ! $this->has('bom_headers', ['bom_code' => 'ACC-BOM3'])) {
            $materialId = (int) (DB::connection($this->conn)->table('materials')->where('material_code', 'ACC-FG02')->value('id') ?? 0);
            if ($materialId > 0) {
                $this->insert('bom_headers', [[
                    'bom_code' => 'ACC-BOM3',
                    'product_material_id' => $materialId,
                    'version' => 'v1.0',
                    'status' => 'draft',
                    'remark' => '演示：待研发经理审核的 BOM',
                    'created_at' => $this->ts(-1),
                    'updated_at' => $ts,
                ]]);
            }
        }

        echo '  admin 个人工作面已补齐（AI 会话 / 研发任务 / 待审 BOM）' . PHP_EOL;
    }
}
