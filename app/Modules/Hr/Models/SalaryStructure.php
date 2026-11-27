<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * salary_structures 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class SalaryStructure extends BaseModel
{
    protected $table = 'salary_structures';

    protected $fillable = [
        'position_id',
        'base',
        'post',
        'performance',
        'allowance',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'base' => 'decimal:2',
            'post' => 'decimal:2',
            'performance' => 'decimal:2',
            'allowance' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }}
