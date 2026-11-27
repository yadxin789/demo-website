<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * payroll_items 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class PayrollItem extends BaseModel
{
    protected $table = 'payroll_items';

    protected $fillable = [
        'run_id',
        'employee_id',
        'employee_no',
        'employee_name',
        'department_id',
        'base',
        'post',
        'performance',
        'allowance',
        'overtime_pay',
        'leave_deduct',
        'social_insurance',
        'tax',
        'gross',
        'net_pay',
    ];

    protected function casts(): array
    {
        return [
            'base' => 'decimal:2',
            'post' => 'decimal:2',
            'performance' => 'decimal:2',
            'allowance' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'leave_deduct' => 'decimal:2',
            'social_insurance' => 'decimal:2',
            'tax' => 'decimal:2',
            'gross' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }}
