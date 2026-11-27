<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * employee_movements 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class EmployeeMovement extends BaseModel
{
    protected $table = 'employee_movements';

    protected $fillable = [
        'employee_id',
        'type',
        'effective_date',
        'from_dept',
        'to_dept',
        'from_position',
        'to_position',
        'operator_id',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_dept');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_dept');
    }

    public function fromPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'from_position');
    }

    public function toPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'to_position');
    }
}
