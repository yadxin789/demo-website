<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI 变更提案。
 *
 * AI 永远不直接改数据：先 proposal（status=pending，仅记录意图与预期 diff），
 * 授权人在「AI 授权中心」审阅后 confirm 才真正执行，reject 则丢弃。
 */
class AiChangeProposal extends BaseModel
{
    protected $fillable = [
        'user_id', 'delegation_token_id', 'resource_type', 'resource_id', 'action',
        'payload', 'before', 'after', 'status', 'reason', 'confirm_token_hash',
        'expires_at', 'resolved_at', 'resolve_note', 'result',
    ];

    protected $casts = [
        'payload' => 'array',
        'before' => 'array',
        'after' => 'array',
        'result' => 'array',
        'expires_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    public const RESOURCE_PRODUCT = 'product';
    public const RESOURCE_PURCHASE_ORDER = 'purchase_order';
    public const RESOURCE_STOCK_ADJUSTMENT = 'stock_adjustment';

    public const TTL_MINUTES = 30;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(AiDelegationToken::class, 'delegation_token_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * 人类可读的动作名
     */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'create' => '新建',
            'update' => '修改',
            'update_price' => '调整售价',
            'adjust_stock' => '库存调整',
            'delete' => '删除',
            'upload' => '上传文件',
            'approve' => '审核通过',
            'reject' => '审核驳回',
            'extension' => '申请延期',
            default => $this->action,
        };
    }

    public function resourceLabel(): string
    {
        return match ($this->resource_type) {
            self::RESOURCE_PRODUCT => '商品资料',
            self::RESOURCE_PURCHASE_ORDER => '采购订单',
            self::RESOURCE_STOCK_ADJUSTMENT => '库存调整',
            default => $this->resource_type,
        };
    }

    /**
     * 供审批界面展示的字段级差异：['field' => ['before' => x, 'after' => y]]
     */
    public function diff(): array
    {
        $before = (array) $this->before;
        $after = (array) $this->after;
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

        $out = [];
        foreach ($keys as $key) {
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;
            if ($b === $a) {
                continue;
            }
            $out[$key] = ['before' => $b, 'after' => $a];
        }
        return $out;
    }
}
