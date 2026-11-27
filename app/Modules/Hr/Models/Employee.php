<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * employees 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class Employee extends BaseModel
{
    protected $table = 'employees';

    protected $fillable = [
        'employee_no',
        'name',
        'gender',
        'birthday',
        'id_card_masked',
        'phone',
        'email',
        'department_id',
        'position_id',
        'hire_date',
        'regular_date',
        'leave_date',
        'status',
        'education',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'hire_date' => 'date',
            'regular_date' => 'date',
            'leave_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class)->orderByDesc('start_date');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EmployeeMovement::class)->orderByDesc('effective_date');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class)->orderByDesc('work_date');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class)->orderByDesc('start_date');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function overtimes(): HasMany
    {
        return $this->hasMany(Overtime::class);
    }

    /** 技能等级（M7 增补） */
    public function skills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class);
    }

    /** 证书与资质（M7 增补） */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /** 培训记录（M7 增补） */
    public function trainingRecords(): HasMany
    {
        return $this->hasMany(TrainingRecord::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }}
