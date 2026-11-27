<?php

declare(strict_types=1);

namespace App\Modules\Mes\Database\Seeders;

use App\Modules\Mes\Models\Routing;
use App\Modules\Mes\Models\SopDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * 工艺文件 SOP 演示数据（M7 增补 2）。
 *
 * 口径：前 5 条工艺路线的全部工序各配一份生效 SOP（约 25 道），
 * 剩余路线的第 1 道工序留"未下发"，用来演示现场登记动作。
 * 幂等：按 code updateOrCreate。
 */
class SopSeeder extends Seeder
{
    public function run(): void
    {
        $routings = Routing::query()->with(['operations' => fn ($q) => $q->orderBy('op_no')])->orderBy('code')->get();

        foreach ($routings as $idx => $routing) {
            $firstOpNo = (int) $routing->operations->min('op_no');

            foreach ($routing->operations as $op) {
                $opNo = (int) $op->op_no;

                // 后 3 条路线的第一道工序故意不配，留缺口
                if ($idx >= 5 && $opNo === $firstOpNo) {
                    continue;
                }

                $code = sprintf('SOP-%s-%02d', $routing->code, $opNo);
                $title = $op->op_name . '作业指导书';

                $content = implode("\n", [
                    "一、作业前准备",
                    "  1. 核对工单号、物料批号与工艺路线 {$routing->code} 一致；",
                    '  2. 检查设备与工装状态，点检表已签字；',
                    '  3. 首件必须报检，合格后方可批量加工。',
                    '',
                    '二、作业步骤',
                    "  1. 按《{$op->op_name}》标准参数设定设备；",
                    '  2. 每 30 件抽检一次，记录实测值；',
                    '  3. 异常立即停线并通知班组长。',
                    '',
                    '三、安全与质量红线',
                    '  1. 防护装置不得拆除，禁止徒手取件；',
                    '  2. 不良品单独隔离，贴红色标识；',
                    '  3. 参数变更必须走工艺变更流程，不得私自调整。',
                ]);

                $withFile = in_array($opNo, [10, 30], true) && $idx < 3;

                $payload = [
                    'title' => $title,
                    'version' => 'A/0',
                    'content' => $content,
                    'status' => 'active',
                    'effective_on' => now()->subMonths(3)->toDateString(),
                    'author' => '工艺科',
                ];

                if ($withFile) {
                    $path = "sop/{$code}.txt";
                    Storage::disk('local')->put($path, "【{$code}】{$title}\n\n{$content}");
                    $payload['file_path'] = $path;
                    $payload['file_name'] = "{$code}.txt";
                }

                SopDocument::query()->updateOrCreate(
                    ['code' => $code],
                    $payload + ['routing_operation_id' => $op->id]
                );
            }
        }
    }
}
