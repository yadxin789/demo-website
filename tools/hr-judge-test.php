<?php

declare(strict_types=1);

/**
 * H2-02 打卡判定自检（不写库）。
 *
 * 用法： php tools/hr-judge-test.php
 * 退出码：0 = 全部通过
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Services\AttendanceService;

$service = new AttendanceService();

$day = Shift::query()->where('code', 'S01')->firstOrFail();   // 09:00-18:00 容忍 5
$night = Shift::query()->where('code', 'S03')->firstOrFail(); // 21:00-06:00 跨天

$cases = [
    ['正常上班', $day, '2026-11-26', '08:58', '18:05', ['normal', 0, 0]],
    ['迟到 15 分（容忍 5）', $day, '2026-11-26', '09:20', '18:00', ['late', 15, 0]],
    ['刚好在容忍内', $day, '2026-11-26', '09:05', '18:00', ['normal', 0, 0]],
    ['早退 60 分', $day, '2026-11-26', '09:00', '17:00', ['early', 0, 60]],
    ['迟到 + 早退，取大的', $day, '2026-11-26', '09:10', '17:30', ['early', 5, 30]],
    ['缺下班卡', $day, '2026-11-26', '09:00', null, ['missing', 0, 0]],
    ['两头都缺', $day, '2026-11-26', null, null, ['missing', 0, 0]],
    ['夜班正常', $night, '2026-11-26', '20:55', '06:10', ['normal', 0, 0]],
    ['夜班迟到 20 分', $night, '2026-11-26', '21:20', '06:00', ['late', 10, 0]],
];

$pass = 0;
$fail = 0;

foreach ($cases as $case) {
    [$name, $shift, $date, $in, $out, $expect] = $case;
    $got = $service->judge($in, $out, $shift, $date);
    $ok = $got['status'] === $expect[0]
        && $got['late_minutes'] === $expect[1]
        && $got['early_minutes'] === $expect[2];

    if ($ok) {
        $pass++;
        printf("  PASS  %-22s → %-7s 迟 %d / 早 %d\n", $name, $got['status'], $got['late_minutes'], $got['early_minutes']);
    } else {
        $fail++;
        printf(
            "  FAIL  %-22s → 期望 %s/%d/%d，实际 %s/%d/%d\n",
            $name,
            $expect[0], $expect[1], $expect[2],
            $got['status'], $got['late_minutes'], $got['early_minutes']
        );
    }
}

printf("\n==== 判定自检：PASS %d / FAIL %d ====\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
