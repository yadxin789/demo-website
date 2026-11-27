<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * attendance_records 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class AttendanceRecord extends BaseModel
{
    protected $table = 'attendance_records';

    protected $fillable = [
        'employee_id',
        'work_date',
        'clock_in',
        'clock_out',
        'shift_id',
        'status',
        'late_minutes',
        'early_minutes',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function exception(): HasOne
    {
        return $this->hasOne(AttendanceException::class, 'attendance_id');
    }}
