<?php

declare(strict_types=1);

/*
 * gen-hr-models.php —— 按 hr 库实际表结构生成 20 个业务模型（H0-09）
 *
 * 只生成一次，生成后可继续手改；重跑会覆盖同名文件。
 * 约束：全部 extends BaseModel（$connection = 'hr'），不出现裸 extends Model（G-02）。
 * 显式写 $table：避免 Laravel 复数化把 overtime / headcount 之类猜错。
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dir = $root . '/app/Modules/Hr/Models';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

/** 表 → 模型类名 */
$map = [
    'departments' => 'Department',
    'positions' => 'Position',
    'headcounts' => 'Headcount',
    'employees' => 'Employee',
    'employee_contracts' => 'EmployeeContract',
    'employee_movements' => 'EmployeeMovement',
    'shifts' => 'Shift',
    'attendance_records' => 'AttendanceRecord',
    'attendance_exceptions' => 'AttendanceException',
    'overtimes' => 'Overtime',
    'leaves' => 'Leave',
    'leave_balances' => 'LeaveBalance',
    'salary_structures' => 'SalaryStructure',
    'payroll_runs' => 'PayrollRun',
    'payroll_items' => 'PayrollItem',
    'requisitions' => 'Requisition',
    'candidates' => 'Candidate',
    'interviews' => 'Interview',
    'performance_cycles' => 'PerformanceCycle',
    'performance_indicators' => 'PerformanceIndicator',
    'performance_reviews' => 'PerformanceReview',
];

$skipCols = ['id', 'created_at', 'updated_at'];

foreach ($map as $table => $model) {
    $cols = DB::connection('hr')->select('PRAGMA table_info("' . $table . '")');
    if (! $cols) {
        fwrite(STDERR, "跳过（表不存在）：{$table}\n");
        continue;
    }

    $fillable = [];
    $casts = [];
    foreach ($cols as $c) {
        $name = $c->name;
        if (in_array($name, $skipCols, true)) {
            continue;
        }
        $fillable[] = $name;

        $type = strtolower((string) $c->type);
        if (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) {
            $casts[$name] = 'datetime';
        } elseif (str_contains($type, 'date')) {
            $casts[$name] = 'date';
        } elseif (str_contains($type, 'decimal') || str_contains($type, 'numeric')) {
            $casts[$name] = 'decimal:2';
        } elseif (str_contains($type, 'bool')) {
            $casts[$name] = 'bool';
        }
    }

    $fillableCode = '';
    foreach ($fillable as $f) {
        $fillableCode .= "        '{$f}',\n";
    }

    $castsCode = '';
    foreach ($casts as $k => $v) {
        $castsCode .= "            '{$k}' => '{$v}',\n";
    }

    $body = '<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

/**
 * ' . $table . ' 表。
 *
 * 由 tools/gen-hr-models.php 按真实表结构生成，可直接手改。
 * 关系方法按需在本类内追加（生成器不猜外键）。
 */
class ' . $model . ' extends BaseModel
{
    protected $table = \'' . $table . '\';

    protected $fillable = [
' . $fillableCode . '    ];

    protected function casts(): array
    {
        return [
' . $castsCode . '        ];
    }
}
';

    file_put_contents($dir . '/' . $model . '.php', $body);
    fwrite(STDOUT, "生成 {$model}.php（" . count($fillable) . " 个字段，" . count($casts) . " 个 cast）\n");
}

fwrite(STDOUT, "\n完成：" . count($map) . " 个模型\n");
