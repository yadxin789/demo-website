<?php

declare(strict_types=1);

/*
 * rewrite-shop-routes.php — 把 E:\erp-shop\routes\web.php 改写成模块路由
 *
 * 源项目路由是全平铺（无 prefix / name group），只有一个 auth 大组；
 * 这里把它整体塞进 prefix('shop') + name('shop.') 组，并把：
 *   middleware('auth')      -> middleware('auth:shop')
 *   middleware('guest')     -> middleware('guest:shop')
 *   middleware('permission: -> middleware('shop.permission:
 *   / 落地页                -> 未登录去 shop.login、已登录去 shop.dashboard（HC-10）
 *
 * 用法： php tools\rewrite-shop-routes.php [--check]
 */

$root   = dirname(__DIR__);
$source = 'E:\\erp-shop\\routes\\web.php';
$target = $root . '\\app\\Modules\\Shop\\Routes\\web.php';
$check  = in_array('--check', $argv ?? [], true);

$raw = file_get_contents($source);
if ($raw === false) {
    fwrite(STDERR, "读取源路由失败: {$source}\n");
    exit(1);
}

$nsMap = [
    'App\\Http\\'       => 'App\\Modules\\Shop\\Http\\',
    'App\\Models\\'     => 'App\\Modules\\Shop\\Models\\',
    'App\\Services\\'   => 'App\\Modules\\Shop\\Services\\',
    'App\\Policies\\'   => 'App\\Modules\\Shop\\Policies\\',
    'App\\Support\\'    => 'App\\Modules\\Shop\\Support\\',
    'App\\Enums\\'      => 'App\\Modules\\Shop\\Enums\\',
    'App\\Ai\\'         => 'App\\Modules\\Shop\\Ai\\',
    'App\\Observers\\'  => 'App\\Modules\\Shop\\Observers\\',
    'App\\Rules\\'      => 'App\\Modules\\Shop\\Rules\\',
    'App\\Exceptions\\' => 'App\\Modules\\Shop\\Exceptions\\',
];

// ---- 1. use 段 ----
preg_match_all('/^use\s+.*?;\s*$/m', $raw, $m);
$uses = implode("\n", $m[0]);
$uses = strtr($uses, $nsMap);

// ---- 2. 主体 ----
$pos  = strpos($raw, "Route::get('/', function");
if ($pos === false) {
    fwrite(STDERR, "未找到根路由块\n");
    exit(1);
}
$body = substr($raw, $pos);

// ---- 3. 根路由换成按产品分流的中间段 ----
$rootBlock = <<<'PHP'
Route::get('/', function () {
    return auth('shop')->check()
        ? redirect()->route('shop.dashboard')
        : redirect()->route('shop.login');
})->name('root');
PHP;
$body = preg_replace("/Route::get\('\/', function \(\) \{.*?\n\}\);/s", $rootBlock, $body, 1, $cnt);
if ($cnt !== 1) {
    fwrite(STDERR, "根路由块替换次数异常: {$cnt}\n");
    exit(1);
}

// ---- 4. 中间件改写 ----
$body = str_replace("middleware('auth')", "middleware('auth:shop')", $body);
$body = str_replace('middleware("auth")', 'middleware("auth:shop")', $body);
$body = str_replace("middleware('guest')", "middleware('guest:shop')", $body);
$body = str_replace('middleware("guest")', 'middleware("guest:shop")', $body);
$body = str_replace("'permission:", "'shop.permission:", $body);
$body = str_replace('"permission:', '"shop.permission:', $body);
// 数组形式：['auth', ...] / ['auth:erp' 之类混写一律兜住
$body = str_replace("['auth',", "['auth:shop',", $body);

// ---- 5. 命名空间 ----
$body = strtr($body, $nsMap);

// ---- 6. 缩进（组内缩 4 格）----
$lines = explode("\n", $body);
$indented = [];
foreach ($lines as $line) {
    $indented[] = ($line === '' ? '' : '    ' . $line);
}
$body = implode("\n", $indented);

$head = "<?php\n\ndeclare(strict_types=1);\n\n";
if (!str_contains($uses, 'Illuminate\\Support\\Facades\\Route')) {
    $uses .= "\nuse Illuminate\\Support\\Facades\\Route;";
}
$head .= $uses . "\n\n"
    . "/*\n|--------------------------------------------------------------------------\n"
    . "| 百货零售 ERP 路由（S3 从 E:\\erp-shop 搬入）\n"
    . "|--------------------------------------------------------------------------\n"
    . "| HC-07：必须同时具备 prefix('shop') 与 name('shop.')\n"
    . "| HC-08：中间件链固定为 web -> module:shop -> product.only:shop\n"
    . "| HC-09：不做旧 URL 兼容；原项目 / 是落地页，这里换成按登录态分流\n"
    . "| 认证：auth:shop / guest:shop；权限：shop.permission:<code>（Shop 自带 RBAC，不走 spatie）\n"
    . "*/\n\n";

$out = $head
    . "Route::middleware(['web', 'module:shop', 'product.only:shop', 'shop.audit'])\n"
    . "    ->prefix('shop')\n"
    . "    ->name('shop.')\n"
    . "    ->group(function () {\n\n"
    . $body . "\n\n});\n";

$routeCount = substr_count($out, 'Route::');
fwrite(STDOUT, "生成路由文件: " . str_pad((string)strlen($out), 8) . " 字节，Route:: 共 {$routeCount} 处\n");

if ($check) {
    fwrite(STDOUT, "（--check 模式，未写入）\n");
    exit(0);
}

file_put_contents($target, $out);
fwrite(STDOUT, "已写入 {$target}\n");
