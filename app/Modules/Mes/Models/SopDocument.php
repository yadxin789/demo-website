<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SOP 工艺文件（MES，M7 增补 2）。
 *
 * 一份 SOP 对应一道工序（routing_operations 的一行），现场按"哪条工艺路线的第几道工序"查阅。
 */
class SopDocument extends BaseModel
{
    public const STATUS = ['active' => '生效', 'draft' => '草稿', 'obsolete' => '作废'];

    protected $fillable = [
        'routing_operation_id', 'code', 'title', 'version', 'content',
        'file_path', 'file_name', 'status', 'effective_on', 'author',
    ];

    protected $casts = ['effective_on' => 'date'];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'routing_operation_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** 是否已到生效日（未到期的算"待生效"） */
    public function isEffective(): bool
    {
        return $this->status === 'active'
            && ($this->effective_on === null || $this->effective_on->lte(now()->startOfDay()));
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null && $this->file_path !== '';
    }
}
