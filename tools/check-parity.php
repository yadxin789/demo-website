<?php

declare(strict_types=1);

/**
 * 参考项目对账（S11，HC-38）
 *
 * 用法：
 *   php tools/check-parity.php erp     # 基线 E:\ddwl-erp\erp
 *   php tools/check-parity.php shop    # 基线 E:\erp-shop
 *
 * 对账三件事：
 *   1) 路由名（去掉产品前缀后比较）
 *   2) 视图文件集合
 *   3) 数据库表名（可选，仅报告）
 *
 * 退出码：0 = 无差异或在白名单内；1 = 有未登记差异。
 */

const BASELINES = [
    'erp' => ['root' => 'E:/ddwl-erp/erp', 'module' => 'Erp'],
    'shop' => ['root' => 'E:/erp-shop', 'module' => 'Shop'],
    'pm' => ['root' => 'E:/my-pm/pm-server-openEuler', 'module' => 'Pm'],
];

// 已知且有意为之的差异：键为归一化名字，值为原因（必须在报告里写清楚）
const ROUTE_WHITELIST = [
    'erp' => [
        'login.submit' => '演示站把 POST 登录单独命名，参考项目直接 POST login',
        'root' => '演示站为门户分流新增的根路由',
        'preview' => '演示站产品预览页',
    ],
    'shop' => [
        'root' => '演示站为门户分流新增的根路由',
        'login.submit' => '演示站把 POST 登录单独命名',
        'preview' => '演示站产品预览页',
        // 以下全部来自参考项目 routes/api.php（AI 外部数据 API，需要 X-AI-Key + 委托令牌）
        // 演示站不对外开放该 API：客户拿不到 key，也不需要，开放反而增加攻击面
        'ai.api.' => 'AI 外部 API 不在演示站开放（需 X-AI-Key）',
        'meta' => '同上：AI 外部 API 的 /ai/meta',
        'overview' => '同上：/ai/overview',
        'products' => '同上：/ai/products',
        'inventory' => '同上：/ai/inventory',
        'sales-orders' => '同上：/ai/sales-orders',
        'purchase-orders' => '同上：/ai/purchase-orders',
        'receivables' => '同上：/ai/receivables',
        'templates' => '同上：/ai/templates',
        'proposals' => '同上：/ai/proposals',
        'proposals.show' => '同上：/ai/proposals/{id}',
        'write.' => '同上：/ai/write 写提案',
        'product-price' => '同上：/ai/write/product-price',
        'purchase-order' => '同上：/ai/write/purchase-order',
    ],
    // PM 只做参考报告，不作为门禁（本产品按用户要求在演示版裁剪了功能）
    'pm' => [
        'root' => '演示站为门户分流新增的根路由',
        'preview' => '演示站产品预览页',
        'login.submit' => '演示站把 POST 登录单独命名',
        'welcome' => '演示站用 landing 取代 welcome',
    ],
];

// 只对这三项做"必须 0 差异"判定；pm 是裁剪过的产品，仅出报告
const ENFORCED = ['erp', 'shop'];

const VIEW_WHITELIST = [
    'erp' => ['landing.blade.php' => '演示站新增的对外首页（S8）'],
    'shop' => ['landing.blade.php' => '演示站把 erp-shop 的 landing 改造为产品首页（S8）'],
    'pm' => ['landing.blade.php' => '演示站新增的对外首页（S8）', 'layouts/app.blade.php' => '演示站改为共享皮肤（S10）'],
];

$product = $argv[1] ?? '';
if (! isset(BASELINES[$product])) {
    fwrite(STDERR, "用法：php tools/check-parity.php [erp|shop|pm]\n");
    exit(2);
}

$base = BASELINES[$product];
$prefix = $product . '.';
$demoRoot = __DIR__ . '/../app/Modules/' . $base['module'];

/** 递归收集文件相对路径（统一成正斜杠，Windows 下也可靠） */
function walk(string $dir, string $ext): array
{
    if (! is_dir($dir)) {
        return [];
    }
    $root = rtrim(str_replace('\\', '/', (string) realpath($dir)), '/');
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && str_ends_with(strtolower($f->getFilename()), '.' . $ext)) {
            $path = str_replace('\\', '/', $f->getPathname());
            $out[] = substr($path, strlen($root) + 1);
        }
    }
    sort($out);
    return $out;
}

/** 从路由文件里抓 ->name('x') */
function routeNames(string $dir): array
{
    $names = [];
    $root = rtrim(str_replace('\\', '/', (string) realpath($dir)), '/');
    foreach (walk($dir, 'php') as $rel) {
        $src = file_get_contents($root . '/' . $rel);
        if (preg_match_all("/->name\(\s*'([^']+)'/", $src, $m)) {
            foreach ($m[1] as $n) {
                if ($n !== '') {
                    $names[] = $n;
                }
            }
        }
    }
    return array_values(array_unique($names));
}

function normalize(string $name): string
{
    return preg_replace('/^(erp|pm|shop|mes|hr)\./', '', $name);
}

/** 白名单命中：精确名优先，其次前缀通配（如 collab.*） */
function wlReason(string $name, array $wl): ?string
{
    if (isset($wl[$name])) {
        return $wl[$name];
    }
    foreach ($wl as $k => $reason) {
        if (str_ends_with($k, '*') && str_starts_with($name, rtrim($k, '*'))) {
            return $reason;
        }
    }
    return null;
}

echo "=== 对账 {$product}（基线 {$base['root']}）===\n\n";

// ---- 1. 路由名 ----
$refRoutes = array_map('normalize', routeNames($base['root'] . '/routes'));
$demoRoutes = array_map('normalize', routeNames($demoRoot . '/Routes'));
$refRoutes = array_values(array_unique($refRoutes));
$demoRoutes = array_values(array_unique($demoRoutes));

$missRoutes = array_values(array_diff($refRoutes, $demoRoutes));
$extraRoutes = array_values(array_diff($demoRoutes, $refRoutes));

$wlRoutes = ROUTE_WHITELIST[$product] ?? [];
$missRoutesHard = array_values(array_filter($missRoutes, static fn (string $n): bool => wlReason($n, $wlRoutes) === null));

echo "[1] 路由名：参考 " . count($refRoutes) . " / 演示站 " . count($demoRoutes) . "\n";
echo '    参考有、演示站缺：' . count($missRoutes) . '（白名单 ' . (count($missRoutes) - count($missRoutesHard)) . '）' . "\n";
foreach ($missRoutesHard as $n) {
    echo "      ✗ {$n}\n";
}
foreach ($missRoutes as $n) {
    $reason = wlReason($n, $wlRoutes);
    if ($reason !== null) {
        echo "      · {$n}（白名单：{$reason}）\n";
    }
}
echo '    演示站新增（参考没有）：' . count($extraRoutes) . "\n";
foreach ($extraRoutes as $n) {
    echo "      + {$n}\n";
}

// ---- 2. 视图 ----
$refViews = walk($base['root'] . '/resources/views', 'blade.php');
$demoViews = walk($demoRoot . '/Resources/views', 'blade.php');
$missViews = array_values(array_diff($refViews, $demoViews));
$wlViews = VIEW_WHITELIST[$product] ?? [];
$missViewsHard = array_values(array_filter($missViews, static fn (string $n): bool => wlReason($n, $wlViews) === null));

echo "\n[2] 视图：参考 " . count($refViews) . ' / 演示站 ' . count($demoViews) . "\n";
echo '    参考有、演示站缺：' . count($missViews) . "\n";
foreach ($missViews as $n) {
    $reason = wlReason($n, $wlViews);
    echo ($reason === null ? '      ✗ ' : '      · ') . $n . ($reason !== null ? "（白名单：{$reason}）" : '') . "\n";
}

// ---- 3. 结论 ----
$fail = count($missRoutesHard) + count($missViewsHard);
$enforced = in_array($product, ENFORCED, true);

if (! $enforced) {
    echo "\n==== 仅报告（{$product} 为按需求裁剪过的产品，不参与门禁）：未登记差异 {$fail} 项 ====\n";
    exit(0);
}

echo "\n==== " . ($fail === 0 ? '对账通过（差异 0）' : "对账未通过（未登记差异 {$fail} 项）") . " ====\n";
exit($fail === 0 ? 0 : 1);
