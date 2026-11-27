<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * candidates 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class Candidate extends BaseModel
{
    protected $table = 'candidates';

    protected $fillable = [
        'requisition_id',
        'name',
        'phone',
        'email',
        'source',
        'years',
        'resume_summary',
        'stage',
        'remark',
    ];

    protected function casts(): array
    {
        return [
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('round');
    }

    public function stageText(): string
    {
        return [
            'screening' => '筛选',
            'interview' => '面试',
            'offer' => '发 offer',
            'onboard' => '已入职',
            'reject' => '已淘汰',
        ][$this->stage] ?? $this->stage;
    }}
