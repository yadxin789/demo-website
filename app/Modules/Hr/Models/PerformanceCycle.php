<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * performance_cycles 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class PerformanceCycle extends BaseModel
{
    protected $table = 'performance_cycles';

    protected $fillable = [
        'name',
        'period',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(PerformanceIndicator::class, 'cycle_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'cycle_id');
    }}
