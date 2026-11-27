<?php

declare(strict_types=1);

// 路由级精对账：比对 HTTP 方法 + URI + action（Controller@method），比只比 name 更准。
// 用法：php tools/compare-routes.php erp|pm|shop

$code = $argv[1] ?? 'erp';

$refFile = __DIR__ . "/baseline/ref-{$code}.json";
if (! is_file($refFile)) {
    fwrite(STDERR, "缺少基线：{$refFile}\n");
    exit(2);
}

$raw = file_get_contents($refFile);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) $raw);
$ref = json_decode($raw, true);
if (! is_array($ref)) {
    fwrite(STDERR, "基线 JSON 解析失败\n");
    exit(2);
}

// demo 侧直接跑 artisan（当前项目）
$demoRaw = shell_exec('C:\\tools\\php83\\php.exe artisan route:list --json --except-vendor 2>&1');
$demo = json_decode((string) $demoRaw, true) ?: [];

$products = ['erp', 'pm', 'shop', 'mes', 'hr'];

/** @return array{0:string,1:string,2:string} [method, uri, action] */
function normalize(array $r, string $code, array $products): array
{
    $uri = '/' . ltrim((string) $r['uri'], '/');
    // demo 去掉自己的产品前缀：/erp/products → /products
    $prefix = '/' . $code;
    if ($uri === $prefix) {
        $uri = '/';
    } elseif (str_starts_with($uri, $prefix . '/')) {
        $uri = substr($uri, strlen($prefix));
    }
    $uri = rtrim($uri, '/');
    if ($uri === '') {
        $uri = '/';
    }
    // 参数名归一化：{id} / {user} 都变成 {*}
    $uri = preg_replace('/\{[^}]+\}/', '{*}', $uri);

    $methods = array_values(array_diff((array) ($r['method'] ?? $r['methods'] ?? []), ['HEAD', 'OPTIONS']));
    if (str_contains((string) ($r['method'] ?? ''), '|')) {
        $methods = explode('|', (string) $r['method']);
        $methods = array_values(array_diff($methods, ['HEAD', 'OPTIONS']));
    }
    sort($methods);

    $action = (string) ($r['action'] ?? '');
    if (preg_match('/\\\\([A-Za-z0-9_]+Controller)@([A-Za-z0-9_]+)/', $action, $m)) {
        $action = $m[1] . '@' . $m[2];
    } elseif (preg_match('/^([A-Za-z0-9_]+)Controller@([A-Za-z0-9_]+)$/', $action, $m)) {
        $action = $m[1] . '@' . $m[2];
    } else {
        $action = 'closure';
    }

    return [implode('|', $methods), $uri, $action];
}

$refKeys = [];
foreach ($ref as $r) {
    [$m, $u, $a] = normalize($r, $code, $products);
    // 多方法路由（PUT|PATCH）拆成单方法键，避免与参考项目里分开注册的两条错判为缺失
    foreach (explode('|', $m) as $one) {
        $refKeys[$one . ' ' . $u] = $a;
    }
}

$demoKeys = [];
foreach ($demo as $r) {
    if (! preg_match('#^/(' . $code . ')(/.*)?$#', '/' . ltrim((string) $r['uri'], '/')) && (string) $r['uri'] !== $code) {
        continue;
    }
    [$m, $u, $a] = normalize($r, $code, $products);
    foreach (explode('|', $m) as $one) {
        $demoKeys[$one . ' ' . $u] = $a;
    }
}

$missing = array_diff_key($refKeys, $demoKeys);
$extra = array_diff_key($demoKeys, $refKeys);

// 同名路由但 action 不同 → 指向了不同控制器方法，也要看
$actionMismatch = [];
foreach ($refKeys as $k => $a) {
    if (isset($demoKeys[$k]) && $demoKeys[$k] !== $a && $a !== 'closure' && $demoKeys[$k] !== 'closure') {
        $actionMismatch[] = "{$k}  参考={$a}   demo={$demoKeys[$k]}";
    }
}

echo "=== {$code} 路由精对账 ===\n";
echo '参考 ' . count($refKeys) . ' 条 / 演示站 ' . count($demoKeys) . " 条\n";
echo '参考有、演示站缺：' . count($missing) . "\n";
foreach ($missing as $k => $a) {
    echo "  ✗ {$k}   （参考 {$a}）\n";
}
echo "\n演示站比参考多：" . count($extra) . "\n";
foreach ($extra as $k => $a) {
    echo "  + {$k}   （演示站 {$a}）\n";
}
echo "\n同路径但控制器方法不同：" . count($actionMismatch) . "\n";
foreach (array_slice($actionMismatch, 0, 30) as $line) {
    echo "  ~ {$line}\n";
}

$out = __DIR__ . "/baseline/diff-{$code}.txt";
$buf = '';
foreach ($missing as $k => $a) {
    $buf .= "MISSING\t{$k}\t{$a}\n";
}
foreach ($extra as $k => $a) {
    $buf .= "EXTRA\t{$k}\t{$a}\n";
}
file_put_contents($out, $buf);
echo "\n明细已写入 tools/baseline/diff-{$code}.txt\n";
