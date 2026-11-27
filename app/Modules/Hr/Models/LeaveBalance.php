<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * leave_balances 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class LeaveBalance extends BaseModel
{
    protected $table = 'leave_balances';

    protected $fillable = [
        'employee_id',
        'year',
        'type',
        'total',
        'used',
        'remaining',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'used' => 'decimal:2',
            'remaining' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }}
