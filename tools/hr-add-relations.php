<?php

declare(strict_types=1);

/*
 * hr-add-relations.php —— 给 gen-hr-models 生成的模型补关系方法（只跑一次，幂等）
 *
 * 生成器不猜外键，关系在这里一次性挂上；已挂过的（方法名已存在）跳过。
 */

$dir = __DIR__ . '/../app/Modules/Hr/Models';

$relations = [
    'Department' => <<<'PHP'
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
PHP,
    'Position' => <<<'PHP'
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
    }
PHP,
    'Headcount' => <<<'PHP'
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
PHP,
    'Employee' => <<<'PHP'
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

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }
PHP,
    'EmployeeContract' => <<<'PHP'
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
PHP,
    'EmployeeMovement' => <<<'PHP'
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
PHP,
    'AttendanceRecord' => <<<'PHP'
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
    }
PHP,
    'AttendanceException' => <<<'PHP'
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
    }
PHP,
    'Overtime' => <<<'PHP'
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
PHP,
    'Leave' => <<<'PHP'
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
PHP,
    'LeaveBalance' => <<<'PHP'
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
PHP,
    'SalaryStructure' => <<<'PHP'
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
PHP,
    'PayrollRun' => <<<'PHP'
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'run_id');
    }
PHP,
    'PayrollItem' => <<<'PHP'
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
PHP,
    'Requisition' => <<<'PHP'
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
PHP,
    'Candidate' => <<<'PHP'
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('round');
    }

    public function stageText(): string
    {
        return [
            'screening' => '筛选',
            'interview' => '面试',
            'offer' => '发 offer',
            'onboard' => '已入职',
            'reject' => '已淘汰',
        ][$this->stage] ?? $this->stage;
    }
PHP,
    'Interview' => <<<'PHP'
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
PHP,
    'PerformanceCycle' => <<<'PHP'
    public function indicators(): HasMany
    {
        return $this->hasMany(PerformanceIndicator::class, 'cycle_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'cycle_id');
    }
PHP,
    'PerformanceIndicator' => <<<'PHP'
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'indicator_id');
    }
PHP,
    'PerformanceReview' => <<<'PHP'
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
    }
PHP,
    'Shift' => <<<'PHP'
    public function attendances(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
PHP,
];

$uses = [];

foreach ($relations as $model => $block) {
    $path = $dir . '/' . $model . '.php';
    $code = (string) file_get_contents($path);

    // 已经加过关系就跳过
    if (preg_match('/public function (parent|department|indicators|candidate|items|employee)\(\)/', $code)) {
        fwrite(STDOUT, "跳过（已有关系）：{$model}\n");
        continue;
    }

    if (! str_contains($code, 'use Illuminate\Database\Eloquent\Relations\HasMany;')) {
        $uses[] = 'HasMany';
        $code = str_replace(
            "namespace App\Modules\Hr\Models;\n",
            "namespace App\\Modules\\Hr\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\nuse Illuminate\\Database\\Eloquent\\Relations\\HasMany;\nuse Illuminate\\Database\\Eloquent\\Relations\\HasOne;\n",
            $code
        );
    }

    $pos = strrpos($code, "}\n");
    $code = substr($code, 0, $pos) . "\n" . $block . "}\n";

    file_put_contents($path, $code);
    fwrite(STDOUT, "补关系：{$model}\n");
}

fwrite(STDOUT, "\n完成\n");
