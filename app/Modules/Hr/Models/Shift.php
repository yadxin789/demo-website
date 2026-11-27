<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * shifts 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class Shift extends BaseModel
{
    protected $table = 'shifts';

    protected $fillable = [
        'code',
        'name',
        'work_start',
        'work_end',
        'late_tolerance_min',
        'cross_day',
        'status',
    ];

    protected function casts(): array
    {
        return [
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }}
