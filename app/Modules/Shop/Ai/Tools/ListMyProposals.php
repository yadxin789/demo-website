<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Models\AiChangeProposal;
use App\Modules\Shop\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListMyProposals implements Tool
{
    public function description(): Stringable|string
    {
        return '列出当前用户最近的 AI 变更提案及状态，便于用户确认或驳回。';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return '无法识别当前用户。';
        }

        $status = trim((string) ($request['status'] ?? ''));
        $rows = AiChangeProposal::where('user_id', $user->id)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return '当前没有 AI 变更提案。';
        }

        $out = "最近 AI 变更提案：\n";
        foreach ($rows as $row) {
            $out .= '- #' . $row->id . '｜' . $row->resourceLabel() . ' / ' . $row->actionLabel()
                . '｜' . $row->status
                . '｜发起 ' . $row->created_at?->format('Y-m-d H:i')
                . ($row->expires_at ? '｜有效期至 ' . $row->expires_at->format('Y-m-d H:i') : '')
                . "\n";
        }
        $out .= '确认入口：' . url('shop/ai-delegation');

        return $out;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('pending / confirmed / rejected / expired / failed，可留空'),
        ];
    }
}
