<?php

namespace App\Modules\Shop\Ai\Concerns;

use App\Modules\Shop\Models\AiChangeProposal;
use App\Modules\Shop\Models\User;
use App\Modules\Shop\Services\AiChangeProposalService;

trait ProposesChanges
{
    protected function currentUser(): ?User
    {
        return auth()->user();
    }

    protected function proposals(): AiChangeProposalService
    {
        return app(AiChangeProposalService::class);
    }

    protected function submit(User $user, string $resourceType, string $action, array $payload, $resource = null): AiChangeProposal
    {
        $reason = $payload['reason'] ?? null;
        unset($payload['reason']);

        return $this->proposals()->propose($user, null, $resourceType, $action, $payload, $reason, $resource);
    }

    protected function renderProposal(AiChangeProposal $proposal): string
    {
        $out = "已生成变更提案 #{$proposal->id}：{$proposal->resourceLabel()} / {$proposal->actionLabel()}\n";
        $out .= "状态：待确认（我没有直接修改任何数据）\n";
        $out .= '确认地址：' . url('shop/ai-delegation') . "\n";
        $out .= '有效期至：' . ($proposal->expires_at?->format('Y-m-d H:i') ?? '—') . "\n";
        if ($proposal->reason) {
            $out .= "变更理由：{$proposal->reason}\n";
        }

        $diff = $proposal->diff();
        if ($diff) {
            $out .= "\n变更差异预览：\n";
            foreach ($diff as $field => $change) {
                $out .= '- ' . $field . '：' . $this->fmt($change['before'])
                    . ' → ' . $this->fmt($change['after']) . "\n";
            }
        }

        $out .= "\n请转告用户：需本人进入 ERP「AI 授权中心」审阅并确认，确认后变更才会真正生效；也可驳回。";

        return $out;
    }

    protected function fmt(mixed $value): string
    {
        if ($value === null) {
            return '（空）';
        }
        if (is_bool($value)) {
            return $value ? '是' : '否';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $text = (string) $value;

        return mb_strlen($text) > 200 ? mb_substr($text, 0, 200) . '…' : $text;
    }
}
