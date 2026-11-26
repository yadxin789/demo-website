<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 不可删除、不可修改的审计日志模型。
 * 仅允许 insert；应用层不提供 update/delete 入口，数据库触发器在 MySQL 层兜底拦截。
 */
class AuditLog extends BaseModel
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_REFUND = 'refund';
    public const ACTION_PRICE_CHANGE = 'price_change';
    public const ACTION_DISCOUNT = 'discount';
    public const ACTION_STOCK_MOVE = 'stock_move';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_EXPORT = 'export';

    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'operator_id', 'acted_at', 'module', 'model', 'record_id', 'document_no',
        'action', 'field', 'old_value', 'new_value', 'source', 'ip',
        'user_agent', 'session_id', 'context', 'message',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
            'record_id' => 'integer',
            'old_value' => 'array',
            'new_value' => 'array',
            'context' => 'array',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * 字段级差异（派生宽表，可由 audit:backfill-diffs 重建）。
     */
    public function fieldDiffs(): HasMany
    {
        return $this->hasMany(AuditFieldDiff::class, 'log_id')->orderBy('id');
    }

    protected static function booted(): void
    {
        // 应用层兜底：禁止更新与删除
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }
}
