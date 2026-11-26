<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 字段级差异派生表（由 audit_logs 计算生成，可重建）。
 */
class AuditFieldDiff extends BaseModel
{
    public const TYPE_ADDED = 'added';

    public const TYPE_CHANGED = 'changed';

    public const TYPE_REMOVED = 'removed';

    protected $table = 'audit_field_diffs';

    public $timestamps = false;

    protected $fillable = [
        'log_id', 'model', 'record_id', 'action', 'field', 'field_label',
        'old_text', 'new_text', 'change_type', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'log_id');
    }
}
