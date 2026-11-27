<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * positions 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class Position extends BaseModel
{
    protected $table = 'positions';

    protected $fillable = [
        'code',
        'name',
        'department_id',
        'grade',
        'status',
        'remark',
    ];

    protected function casts(): array
    {
        return [
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function structure(): HasOne
    {
        return $this->hasOne(SalaryStructure::class);
    }}
