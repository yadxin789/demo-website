<?php

/**
 * 列出某产品的演示账号及其角色（JSON），供 tools/check-role-nav.ps1 使用。
 *
 *   php tools/list-users.php erp
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$code = $argv[1] ?? 'erp';

$models = [
    'erp' => App\Modules\Erp\Models\User::class,
    'pm' => App\Modules\Pm\Models\User::class,
    'shop' => App\Modules\Shop\Models\User::class,
    'mes' => App\Modules\Mes\Models\User::class,
    'hr' => App\Modules\Hr\Models\User::class,
];

$cls = $models[$code] ?? null;
if (! $cls) {
    fwrite(STDERR, "未知产品: {$code}\n");
    exit(1);
}

$db = DB::connection($code);

$roleOf = function ($user) use ($code, $db): array {
    if ($code === 'shop') {
        return $db->table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)->pluck('roles.name')->all();
    }
    $roleTable = $db->getSchemaBuilder()->hasTable('spatie_roles') ? 'spatie_roles' : 'roles';

    return $db->table('model_has_roles')->join($roleTable, "{$roleTable}.id", '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->id)->pluck("{$roleTable}.name")->all();
};

$rows = [];
foreach ($cls::query()->get() as $u) {
    $rows[] = [
        'id' => $u->id,
        'username' => $u->username ?: $u->email,
        'email' => $u->email,
        'name' => $u->name,
        'roles' => $roleOf($u),
    ];
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

