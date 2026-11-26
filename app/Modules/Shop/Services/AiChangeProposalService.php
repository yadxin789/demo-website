<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Models\AiChangeProposal;
use App\Modules\Shop\Models\AiDelegationToken;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\User;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Purchase\PurchaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI 变更提案引擎（ERP 版）。
 *
 * AI 只能生成 pending 提案，不直接写业务数据；授权人在「AI 授权中心」确认后
 * 才按当前业务服务执行，执行结果仍写审计日志。
 */
class AiChangeProposalService
{
    public function __construct(
        protected PurchaseService $purchases,
        protected AuditLogger $audit
    ) {
    }

    public function propose(
        User $user,
        ?AiDelegationToken $token,
        string $resourceType,
        string $action,
        array $payload,
        ?string $reason = null,
        ?Model $resource = null,
        ?int $ttlMinutes = null,
    ): AiChangeProposal {
        [$before, $after] = $this->preview($resourceType, $action, $payload, $resource);

        return AiChangeProposal::create([
            'user_id' => $user->id,
            'delegation_token_id' => $token?->id,
            'resource_type' => $resourceType,
            'resource_id' => $resource?->getKey(),
            'action' => $action,
            'payload' => $payload,
            'before' => $before,
            'after' => $after,
            'status' => AiChangeProposal::STATUS_PENDING,
            'reason' => $reason,
            'expires_at' => now()->addMinutes(max(1, $ttlMinutes ?? AiChangeProposal::TTL_MINUTES)),
        ]);
    }

    /**
     * @return array{0:?array,1:?array}
     */
    protected function preview(string $resourceType, string $action, array $payload, ?Model $resource): array
    {
        if ($resourceType === AiChangeProposal::RESOURCE_PRODUCT && $resource instanceof Product) {
            $before = [
                '商品编码' => $resource->code,
                '商品名称' => $resource->name,
                '当前售价' => (float) $resource->list_price,
            ];
            $after = $before;

            if ($action === 'update_price') {
                $after['当前售价'] = round((float) ($payload['list_price'] ?? 0), 4);
            }

            return [$before, $after];
        }

        if ($resourceType === AiChangeProposal::RESOURCE_PURCHASE_ORDER && $action === 'create') {
            $linePreview = collect($payload['lines'] ?? [])->map(function (array $line) {
                $product = Product::find((int) ($line['product_id'] ?? 0));

                return [
                    '商品' => $product?->name,
                    '数量' => (float) ($line['qty'] ?? 0),
                    '单价' => (float) ($line['unit_price'] ?? 0),
                ];
            })->all();

            $after = [
                '供应商ID' => (int) ($payload['supplier_id'] ?? 0),
                '仓库ID' => (int) ($payload['warehouse_id'] ?? 0),
                '预计到货' => $payload['expected_date'] ?? null,
                '明细' => $linePreview,
                '备注' => $payload['note'] ?? null,
                '落库状态' => '草稿（确认后创建）',
            ];

            return [null, $after];
        }

        return [null, $payload];
    }

    public function confirm(AiChangeProposal $proposal, User $user, ?string $note = null): array
    {
        if ($proposal->user_id !== $user->id && ! $user->isAdmin()) {
            return ['ok' => false, 'message' => '只能确认本人发起的 AI 变更提案。'];
        }
        if (! $proposal->isPending()) {
            return ['ok' => false, 'message' => '该提案已处理，不能重复确认。'];
        }
        if ($proposal->isExpired()) {
            $proposal->update(['status' => AiChangeProposal::STATUS_EXPIRED, 'resolved_at' => now()]);
            return ['ok' => false, 'message' => '提案已过期，请重新发起。'];
        }

        try {
            $result = DB::transaction(function () use ($proposal, $user, $note) {
                if ($proposal->resource_type === AiChangeProposal::RESOURCE_PRODUCT
                    && $proposal->action === 'update_price') {
                    return $this->confirmProductPrice($proposal, $user, $note);
                }

                if ($proposal->resource_type === AiChangeProposal::RESOURCE_PURCHASE_ORDER
                    && $proposal->action === 'create') {
                    return $this->confirmPurchaseOrder($proposal, $user, $note);
                }

                throw new \InvalidArgumentException('暂不支持该提案类型。');
            });

            return ['ok' => true, 'message' => '变更已确认并执行。', 'result' => $result];
        } catch (\Throwable $e) {
            $proposal->update([
                'status' => AiChangeProposal::STATUS_FAILED,
                'resolved_at' => now(),
                'resolve_note' => $e->getMessage(),
            ]);
            Log::error('AI proposal confirm failed', ['proposal_id' => $proposal->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => '执行失败：' . $e->getMessage()];
        }
    }

    protected function confirmProductPrice(AiChangeProposal $proposal, User $user, ?string $note): array
    {
        $product = Product::find($proposal->resource_id);
        if (! $product) {
            throw new \InvalidArgumentException('商品不存在或已被删除。');
        }

        $price = round((float) ($proposal->payload['list_price'] ?? 0), 4);
        if ($price <= 0) {
            throw new \InvalidArgumentException('售价必须大于 0。');
        }

        $old = (float) $product->list_price;
        $product->update(['list_price' => $price]);

        $this->audit->record([
            'module' => 'master',
            'action' => AuditLog::ACTION_UPDATE,
            'model' => Product::class,
            'record_id' => $product->id,
            'document_no' => $product->code,
            'field' => 'list_price',
            'old_value' => $old,
            'new_value' => $price,
            'source' => 'ai',
            'operator_id' => $user->id,
            'message' => "AI 提案 #{$proposal->id} 确认后调整商品售价" . ($note ? "：{$note}" : ''),
        ]);

        $proposal->update([
            'status' => AiChangeProposal::STATUS_CONFIRMED,
            'resolved_at' => now(),
            'resolve_note' => $note,
            'result' => ['product_id' => $product->id, 'list_price' => $price],
        ]);

        return ['product_id' => $product->id, 'list_price' => $price];
    }

    protected function confirmPurchaseOrder(AiChangeProposal $proposal, User $user, ?string $note): array
    {
        $payload = (array) $proposal->payload;
        $lines = array_values(array_filter((array) ($payload['lines'] ?? [])));
        if ($lines === []) {
            throw new \InvalidArgumentException('采购明细不能为空。');
        }

        $order = $this->purchases->createOrder([
            'supplier_id' => (int) $payload['supplier_id'],
            'warehouse_id' => (int) $payload['warehouse_id'],
            'order_date' => $payload['order_date'] ?? Carbon::today()->toDateString(),
            'expected_date' => $payload['expected_date'] ?? null,
            'note' => trim(($payload['note'] ?? '') . ' [AI提案#' . $proposal->id . ']'),
            'creator_id' => $user->id,
        ], $lines);

        $proposal->update([
            'status' => AiChangeProposal::STATUS_CONFIRMED,
            'resolved_at' => now(),
            'resolve_note' => $note,
            'result' => ['purchase_order_id' => $order->id, 'code' => $order->code],
        ]);

        return ['purchase_order_id' => $order->id, 'code' => $order->code];
    }

    public function reject(AiChangeProposal $proposal, User $user, ?string $note = null): array
    {
        if ($proposal->user_id !== $user->id && ! $user->isAdmin()) {
            return ['ok' => false, 'message' => '只能驳回本人发起的 AI 变更提案。'];
        }
        if (! $proposal->isPending()) {
            return ['ok' => false, 'message' => '该提案已处理。'];
        }

        $proposal->update([
            'status' => AiChangeProposal::STATUS_REJECTED,
            'resolved_at' => now(),
            'resolve_note' => $note,
        ]);

        return ['ok' => true, 'message' => '提案已驳回，业务数据未修改。'];
    }

    public function expireStale(): int
    {
        return AiChangeProposal::query()
            ->where('status', AiChangeProposal::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'status' => AiChangeProposal::STATUS_EXPIRED,
                'resolved_at' => now(),
            ]);
    }
}
