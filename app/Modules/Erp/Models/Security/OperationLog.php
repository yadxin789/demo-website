<?php

namespace App\Modules\Erp\Models\Security;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 普通 CRUD 的统一操作流水。
 * 与 AuditLog（审批专用）、LoginLog（登录）、SecurityEvent（越权/异常）互补。
 */
class OperationLog extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;

    protected $table = 'operation_logs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'actor_name',
        'action',
        'target_type',
        'target_id',
        'target_label',
        'changes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Erp\Models\Organization\User::class, 'user_id');
    }

    public function getActionLabelAttribute(): string
    {
        return [
            'created' => '新增',
            'updated' => '修改',
            'deleted' => '删除',
        ][$this->action] ?? $this->action;
    }
}
