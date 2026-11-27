<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * performance_reviews 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class PerformanceReview extends BaseModel
{
    protected $table = 'performance_reviews';

    protected $fillable = [
        'cycle_id',
        'employee_id',
        'indicator_id',
        'self_score',
        'manager_score',
        'final_score',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'final_score' => 'decimal:2',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicator::class, 'indicator_id');
    }}
