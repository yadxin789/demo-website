<?php

declare(strict_types=1);

/**
 * 五人设一致性核查（S7-05 / HC-32 / HC-M04）
 *
 * 只读数据库，不做任何写入。检查三项：
 *   1. 五产品公司名互不重复
 *   2. 五产品用户姓名（以及 HR 员工姓名）两两无交集
 *   3. 五产品业务编码前缀互不重叠
 *
 * 用法：php tools/persona-check.php
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$products = require $base . '/config/products.php';
$defs = $products['products'];

$pass = 0;
$fail = 0;

function ok(string $msg): void
{
    global $pass;
    $pass++;
    echo "  PASS  {$msg}\n";
}

function bad(string $msg): void
{
    global $fail;
    $fail++;
    echo "  FAIL  {$msg}\n";
}

echo "==== 五人设一致性核查 ====\n\n";

// ---------------------------------------------------------------- 1. 公司名
echo "[1] 五产品公司名\n";
$companies = [];
foreach ($defs as $code => $p) {
    if (! ($p['enabled'] ?? false)) {
        continue;
    }
    $companies[$code] = $p['company'] ?? '';
}

$dupes = array_diff_assoc($companies, array_unique($companies));
if ($dupes === []) {
    ok('五家公司名互不重复：' . implode(' / ', $companies));
} else {
    bad('公司名重复：' . implode('、', $dupes));
}

// 公司名不得在别家产品的库里出现（反向串场景）
foreach ($companies as $code => $name) {
    foreach ($companies as $otherCode => $otherName) {
        if ($code === $otherCode || $name === '' || $otherName === '') {
            continue;
        }
        $conn = $otherCode;
        try {
            $hit = DB::connection($conn)->table('users')->where('name', $name)->count();
        } catch (Throwable) {
            $hit = 0;
        }

        if ($hit > 0) {
            bad("{$name} 出现在 {$otherCode} 的用户表里");
        }
    }
}
ok('公司名未出现在其它产品的用户表');

// ---------------------------------------------------------------- 2. 人名
echo "\n[2] 用户与员工姓名\n";
$names = [];

foreach ($defs as $code => $p) {
    if (! ($p['enabled'] ?? false)) {
        continue;
    }

    $conn = $code;
    $set = [];

    try {
        $set = DB::connection($conn)->table('users')->pluck('name')->filter()->all();
    } catch (Throwable $e) {
        bad("{$code} users 读取失败：" . $e->getMessage());
    }

    $names[$code] = $set;
}

foreach ($names as $code => $set) {
    echo '  ' . str_pad($code, 5) . count($set) . ' 人：' . implode('、', array_slice($set, 0, 6)) . (count($set) > 6 ? ' …' : '') . "\n";
}

$overlapFound = false;
$codes = array_keys($names);
for ($i = 0; $i < count($codes); $i++) {
    for ($j = $i + 1; $j < count($codes); $j++) {
        $a = $codes[$i];
        $b = $codes[$j];
        $inter = array_intersect($names[$a], $names[$b]);

        if ($inter !== []) {
            bad("{$a} 与 {$b} 用户重名：" . implode('、', $inter));
            $overlapFound = true;
        }
    }
}

if (! $overlapFound) {
    ok('五产品用户姓名两两无交集');
}

// HR 员工表 vs 其它产品用户表（HC-M04：HR 人名不得与 ERP 重复）
try {
    $empNames = DB::connection('hr')->table('employees')->pluck('name')->filter()->unique()->all();
    $conflict = [];

    foreach (['erp', 'pm', 'shop', 'mes'] as $other) {
        $inter = array_intersect($empNames, $names[$other] ?? []);

        if ($inter !== []) {
            $conflict[$other] = $inter;
        }
    }

    if ($conflict === []) {
        ok('HR 员工姓名（' . count($empNames) . ' 人）与其它四产品用户无交集');
    } else {
        foreach ($conflict as $other => $inter) {
            bad("HR 员工与 {$other} 用户重名：" . implode('、', $inter));
        }
    }
} catch (Throwable $e) {
    bad('HR 员工表读取失败：' . $e->getMessage());
}

// ---------------------------------------------------------------- 3. 编码前缀
echo "\n[3] 业务编码前缀\n";
$probes = [
    'hr' => [
        ['employees', 'employee_no', 'HR2026'],
        ['leaves', 'leave_no', 'LV-2026'],
        ['payroll_runs', 'period', '2026-'],
        ['requisitions', 'req_no', 'REQ-2026'],
    ],
    'mes' => [
        ['work_orders', 'order_no', 'WO-'],
    ],
    'erp' => [
        ['users', 'username', ''],
    ],
];

foreach ($probes as $code => $list) {
    foreach ($list as [$table, $column, $prefix]) {
        try {
            $sample = DB::connection($code)->table($table)->orderBy($column)->value($column);

            if ($sample === null) {
                bad("{$code}.{$table}.{$column} 没有数据");
                continue;
            }

            if ($prefix !== '' && ! str_starts_with((string) $sample, $prefix)) {
                bad("{$code}.{$table}.{$column} 首值 {$sample} 不以 {$prefix} 开头");
                continue;
            }

            ok("{$code}.{$table}.{$column} 前缀 {$prefix}（首值 {$sample}）");
        } catch (Throwable $e) {
            bad("{$code}.{$table} 读取失败：" . $e->getMessage());
        }
    }
}

echo "\n==== 汇总：PASS {$pass} / FAIL {$fail} ====\n";
exit($fail > 0 ? 1 : 0);

