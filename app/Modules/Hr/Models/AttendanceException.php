<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * attendance_exceptions 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class AttendanceException extends BaseModel
{
    protected $table = 'attendance_exceptions';

    protected $fillable = [
        'attendance_id',
        'employee_id',
        'type',
        'work_date',
        'reason',
        'status',
        'result',
        'handler_id',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'handled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }}
