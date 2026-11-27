<?php

/**
 * 演示数据覆盖度审计（可复用）。
 *
 *   php tools/audit-demo-coverage.php             # 用户/角色 + 空表
 *   php tools/audit-demo-coverage.php --tables    # 再列出每张空表的列（用于补种）
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$withTables = in_array('--tables', $argv, true);

$frameworks = [
    'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
    'password_reset_tokens', 'personal_access_tokens', 'migrations',
];

$models = [
    'erp' => App\Modules\Erp\Models\User::class,
    'pm' => App\Modules\Pm\Models\User::class,
    'shop' => App\Modules\Shop\Models\User::class,
    'mes' => App\Modules\Mes\Models\User::class,
    'hr' => App\Modules\Hr\Models\User::class,
];

/** Shop 用自定义 role_user 表，其余走 spatie */
function roleNameOf(string $code, $user): array
{
    $db = DB::connection($code);

    if ($code === 'shop') {
        return $db->table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)->pluck('roles.name')->all();
    }

    $roleTable = $db->getSchemaBuilder()->hasTable('spatie_roles') ? 'spatie_roles' : 'roles';
    $pivot = $db->getSchemaBuilder()->hasTable('model_has_roles') ? 'model_has_roles' : null;

    if ($pivot === null) {
        return [];
    }

    return $db->table($pivot)->join($roleTable, "{$roleTable}.id", '=', "{$pivot}.role_id")
        ->where("{$pivot}.model_id", $user->id)->pluck("{$roleTable}.name")->all();
}

echo str_repeat('=', 60) . PHP_EOL;
echo "一、用户与角色覆盖" . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;

foreach ($models as $code => $cls) {
    $users = $cls::query()->get();
    $counts = [];
    $noRole = [];
    foreach ($users as $u) {
        $roles = collect(roleNameOf($code, $u));
        if ($roles->isEmpty()) {
            $noRole[] = $u->username;
            continue;
        }
        foreach ($roles as $r) {
            $counts[$r] = ($counts[$r] ?? 0) + 1;
        }
    }
    echo "[{$code}] 用户 {$users->count()} 人，角色 " . count($counts) . " 个，无角色 " . count($noRole) . " 人" . PHP_EOL;
    ksort($counts);
    foreach ($counts as $r => $n) {
        echo "    {$r} => {$n} 人" . PHP_EOL;
    }
    if ($noRole) {
        echo "    无角色账号: " . implode('、', array_slice($noRole, 0, 12)) . PHP_EOL;
    }
    echo PHP_EOL;
}

echo str_repeat('=', 60) . PHP_EOL;
echo "二、空表（业务表，排除框架表）" . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;

foreach (array_keys($models) as $code) {
    $db = DB::connection($code);
    $tables = collect($db->select("select name from sqlite_master where type='table' and name not like 'sqlite_%'"))
        ->pluck('name')
        ->reject(fn ($t) => in_array($t, $frameworks, true))
        ->values();

    $empty = [];
    foreach ($tables as $t) {
        $cols = $db->getSchemaBuilder()->getColumnListing($t);
        if ($db->table($t)->count() === 0) {
            $empty[$t] = $cols;
        }
    }

    echo "[{$code}] 业务表 " . count($tables) . " 张，空表 " . count($empty) . " 张" . PHP_EOL;
    foreach ($empty as $t => $cols) {
        echo "    - {$t}" . ($withTables ? ' (' . implode(',', array_slice($cols, 0, 14)) . ')' : '') . PHP_EOL;
    }
    echo PHP_EOL;
}
