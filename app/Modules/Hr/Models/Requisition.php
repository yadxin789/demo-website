<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * requisitions 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class Requisition extends BaseModel
{
    protected $table = 'requisitions';

    protected $fillable = [
        'req_no',
        'position_id',
        'department_id',
        'headcount',
        'hired',
        'urgency',
        'status',
        'requester_id',
        'remark',
        'raised_on',
    ];

    protected function casts(): array
    {
        return [
            'raised_on' => 'date',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }}
