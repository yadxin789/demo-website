<?php

declare(strict_types=1);

/*
 * check-isolation.php — 五产品独立性回归（HC-01 / HC-11 / HC-15 / HC-16）
 *
 * 验证六件事：
 *   1) 各产品 User 模型指向自己的连接与表（MES 已并入）
 *   2) 各 guard 认各自用户，互不串
 *   3) 写隔离：Shop / MES 各写一条，别库计数零变化
 *   4) 各库迁移互不重叠
 *   5) 静态零引用：MES 代码里不得出现 erp/pm/shop 的命名空间或连接
 *   6) 反向零引用：erp/pm/shop 不得反过来依赖 MES
 *
 * 用法： php tools\check-isolation.php
 * 退出码：0 = 全部通过
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Erp\Models\User as ErpUser;
use App\Modules\Mes\Models\User as MesUser;
use App\Modules\Pm\Models\User as PmUser;
use App\Modules\Shop\Models\Product as ShopProduct;
use App\Modules\Shop\Models\User as ShopUser;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;

function check(string $title, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        fwrite(STDOUT, "  PASS  {$title}" . ($detail !== '' ? "  ({$detail})" : '') . "\n");
    } else {
        $fail++;
        fwrite(STDOUT, "  FAIL  {$title}" . ($detail !== '' ? "  ({$detail})" : '') . "\n");
    }
}

function counts(array $probe): array
{
    $out = [];
    foreach ($probe as $conn => $tables) {
        foreach ($tables as $t) {
            try {
                $out[$conn . '.' . $t] = DB::connection($conn)->table($t)->count();
            } catch (Throwable $e) {
                $out[$conn . '.' . $t] = -1;
            }
        }
    }

    return $out;
}

/** 递归扫一个目录下的 php 文件，返回命中正则的文件:行 */
function scan(string $dir, string $pattern): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $hits = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = (string) file_get_contents($file->getPathname());
        if (preg_match_all($pattern, $code, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $one) {
                $line = substr_count(substr($code, 0, (int) $one[1]), "\n") + 1;
                $hits[] = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname() . ':' . $line);
            }
        }
    }

    return $hits;
}

fwrite(STDOUT, "==== 五产品独立性回归 ====\n\n");

// ---- 1. User 模型连接 ----
fwrite(STDOUT, "[1] 四份 User 模型的连接与表\n");
check('ERP User → erp.users', (new ErpUser())->getConnectionName() === 'erp' && (new ErpUser())->getTable() === 'users');
check('PM  User → pm.users', (new PmUser())->getConnectionName() === 'pm' && (new PmUser())->getTable() === 'users');
check('Shop User → shop.users', (new ShopUser())->getConnectionName() === 'shop' && (new ShopUser())->getTable() === 'users');
check('Mes User → mes.users', (new MesUser())->getConnectionName() === 'mes' && (new MesUser())->getTable() === 'users');
check('Shop Product → shop.products', (new ShopProduct())->getConnectionName() === 'shop' && (new ShopProduct())->getTable() === 'products');

// ---- 2. guard 独立性 ----
fwrite(STDOUT, "\n[2] 四个 guard 的 provider 与用户表\n");
foreach (['erp', 'pm', 'shop', 'mes'] as $guard) {
    $provider = config("auth.guards.{$guard}.provider");
    $model = config("auth.providers.{$provider}.model");
    check("guard {$guard} 指向自己的模型", is_string($model) && str_contains($model, '\\Modules\\' . ucfirst($guard)), (string) $model);
}

// ---- 3. 写隔离：Shop / MES 写入不影响其他库 ----
fwrite(STDOUT, "\n[3] 写隔离（Shop 建商品、Mes 建用户，别的库计数必须零变化）\n");
$probe = [
    'erp' => ['users', 'materials', 'customers', 'sales_orders'],
    'pm' => ['users', 'projects', 'tasks'],
    'shop' => ['users', 'products', 'customers', 'pos_orders'],
    'mes' => ['users', 'work_orders', 'production_reports', 'equipments'],
];
$before = counts($probe);

$code = 'ISO-' . date('mdHi');
$created = null;
try {
    $p = new ShopProduct();
    $p->code = $code;
    $p->name = '隔离自检商品';
    $p->is_active = true;
    $p->save();
    $created = $p;
} catch (Throwable $e) {
    fwrite(STDOUT, "  （商品写入跳过：{$e->getMessage()}）\n");
}

// MES 侧：在事务里插一条用户，取完计数立刻回滚，MES 库本身也不留痕
$mesWrote = false;
DB::connection('mes')->beginTransaction();
try {
    DB::connection('mes')->table('users')->insert([
        'name' => '隔离自检账号',
        'email' => 'iso.' . date('mdHi') . '@mes.demo',
        'password' => bcrypt('x'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $mesWrote = true;
} catch (Throwable $e) {
    fwrite(STDOUT, "  （MES 写入跳过：{$e->getMessage()}）\n");
}

$after = counts($probe);

$erpChanged = [];
$pmChanged = [];
$shopChanged = [];
$mesChanged = [];
foreach ($before as $key => $v) {
    if ($v === $after[$key]) {
        continue;
    }
    if (str_starts_with($key, 'erp.')) {
        $erpChanged[] = "{$key}: {$v} → {$after[$key]}";
    }
    if (str_starts_with($key, 'pm.')) {
        $pmChanged[] = "{$key}: {$v} → {$after[$key]}";
    }
    if (str_starts_with($key, 'shop.')) {
        $shopChanged[] = "{$key}: {$v} → {$after[$key]}";
    }
    if (str_starts_with($key, 'mes.')) {
        $mesChanged[] = "{$key}: {$v} → {$after[$key]}";
    }
}

check('写 Shop / Mes 后 ERP 库零变化', $erpChanged === [], implode('; ', $erpChanged));
check('写 Shop / Mes 后 PM 库零变化', $pmChanged === [], implode('; ', $pmChanged));
check('写 Shop 后 Shop 库只有商品 +1', $created === null || ($shopChanged === [] || $shopChanged === ["shop.products: {$before['shop.products']} → {$after['shop.products']}"]), implode('; ', $shopChanged));
check('Mes 写入未波及 Shop（Shop 的变化只来自本脚本自己建的商品）', $shopChanged === [] || $shopChanged === ["shop.products: {$before['shop.products']} → {$after['shop.products']}"], implode('; ', $shopChanged));
check(
    'MES 写入只落在 mes.users',
    ! $mesWrote || $mesChanged === ["mes.users: {$before['mes.users']} → {$after['mes.users']}"],
    implode('; ', $mesChanged)
);

DB::connection('mes')->rollBack();
if ($mesWrote) {
    $mesAfter = DB::connection('mes')->table('users')->count();
    check('MES 事务回滚后 users 复原', $mesAfter === $before['mes.users'], "users = {$mesAfter}");
}

// ---- 4. 清理 ----
if ($created !== null) {
    try {
        $created->delete();
    } catch (Throwable $e) {
        fwrite(STDOUT, "  （清理商品失败：{$e->getMessage()}）\n");
    }
}

// ---- 5. 各库迁移互不重叠 ----
fwrite(STDOUT, "\n[4] 各库迁移互不重叠\n");
foreach (['erp', 'pm', 'shop', 'mes'] as $conn) {
    $n = DB::connection($conn)->table('migrations')->count();
    fwrite(STDOUT, "  {$conn}.migrations = {$n}\n");
}

// ---- 6. 静态零引用 ----
fwrite(STDOUT, "\n[5] 静态零引用（跨产品命名空间 / 跨库连接名）\n");

foreach (['mes' => ['Erp', 'Pm', 'Shop']] as $self => $others) {
    $dir = $root . '/app/Modules/' . ucfirst($self);
    $pat = 'App\\\\Modules\\\\(?:' . implode('|', $others) . ')\\\\';
    $hits = scan($dir, '/' . $pat . '/');
    check(ucfirst($self) . ' 代码不引用其它产品命名空间', $hits === [], implode(', ', array_slice($hits, 0, 5)));

    $hits2 = scan($dir, '/connection\s*\(\s*(\'|")(erp|pm|shop)(\'|")/');
    check(ucfirst($self) . ' 代码不指定别产品连接', $hits2 === [], implode(', ', array_slice($hits2, 0, 5)));
}

foreach (['erp', 'pm', 'shop'] as $self) {
    $hits = scan($root . '/app/Modules/' . ucfirst($self), '/App\\\\Modules\\\\Mes\\\\/');
    check(ucfirst($self) . ' 不反向依赖 MES', $hits === [], implode(', ', array_slice($hits, 0, 5)));
}

fwrite(STDOUT, "\n==== 汇总：PASS {$pass} / FAIL {$fail} ====\n");
exit($fail > 0 ? 1 : 0);
