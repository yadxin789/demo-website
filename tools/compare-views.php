<?php

declare(strict_types=1);

// 视图内容对账：同名视图里，参考项目用到的 route('x.y') 演示站是否也有。
// 用来发现"页面在、但按钮/链接没接"的空壳视图。
// 用法：php tools/compare-views.php erp|pm|shop

$code = $argv[1] ?? 'erp';

$map = [
    'erp' => ['ref' => 'E:/ddwl-erp/erp/resources/views', 'demo' => __DIR__ . '/../app/Modules/Erp/Resources/views'],
    'pm' => ['ref' => 'E:/my-pm/pm-server-openEuler/resources/views', 'demo' => __DIR__ . '/../app/Modules/Pm/Resources/views'],
    'shop' => ['ref' => 'E:/erp-shop/resources/views', 'demo' => __DIR__ . '/../app/Modules/Shop/Resources/views'],
];

if (! isset($map[$code])) {
    fwrite(STDERR, "未知产品：{$code}\n");
    exit(2);
}

/** @return array<string,string> 相对名 => 文件路径 */
function collectViews(string $dir): array
{
    $out = [];
    if (! is_dir($dir)) {
        return $out;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
        $rel = preg_replace('/\.blade\.php$/', '', $rel);
        $out[$rel] = $f->getPathname();
    }

    return $out;
}

function routesIn(string $file, string $code): array
{
    $text = (string) file_get_contents($file);
    preg_match_all("/route\(\s*'([a-zA-Z0-9_.\-]+)'/", $text, $m);
    $names = [];
    foreach ($m[1] as $name) {
        // 演示站路由名带产品前缀（erp.xxx），参考项目是裸名（xxx），统一去掉前缀
        if (str_starts_with($name, $code . '.')) {
            $name = substr($name, strlen($code) + 1);
        }
        $names[$name] = true;
    }
    $names = array_keys($names);
    sort($names);

    return $names;
}

$ref = collectViews($map[$code]['ref']);
$demo = collectViews($map[$code]['demo']);

echo "=== {$code} 视图内容对账 ===\n";
echo '参考视图 ' . count($ref) . ' / 演示站视图 ' . count($demo) . "\n\n";

$notFound = [];
$thin = [];
$ok = 0;

foreach ($ref as $rel => $path) {
    $demoFile = $demo[$rel] ?? null;
    if ($demoFile === null) {
        // 按文件名兜底匹配
        $base = basename($rel);
        foreach ($demo as $drel => $dpath) {
            if (basename($drel) === $base) {
                $demoFile = $dpath;
                break;
            }
        }
    }
    if ($demoFile === null) {
        $notFound[] = $rel;
        continue;
    }

    $refRoutes = routesIn($path, $code);
    if ($refRoutes === []) {
        continue;
    }
    $demoRoutes = routesIn($demoFile, $code);
    $miss = array_values(array_diff($refRoutes, $demoRoutes));

    if (count($miss) >= max(3, (int) ceil(count($refRoutes) * 0.6))) {
        $thin[] = ['view' => $rel, 'miss' => count($miss), 'total' => count($refRoutes), 'list' => array_slice($miss, 0, 8)];
    } else {
        $ok++;
    }
}

echo '参考有、演示站没对应文件的视图：' . count($notFound) . "\n";
foreach ($notFound as $v) {
    echo "  ✗ {$v}\n";
}

echo "\n需要重点看（缺 60% 以上 route 引用）：" . count($thin) . "\n";
foreach (array_slice($thin, 0, 40) as $t) {
    echo "  ! {$t['view']}   缺 {$t['miss']}/{$t['total']}  例：" . implode(', ', $t['list']) . "\n";
}
echo "\n内容基本对得上的视图：" . $ok . "\n";

$buf = '';
foreach ($notFound as $v) {
    $buf .= "NO_VIEW\t{$v}\n";
}
foreach ($thin as $t) {
    $buf .= "THIN\t{$t['view']}\t{$t['miss']}/{$t['total']}\n";
}
file_put_contents(__DIR__ . "/baseline/views-{$code}.txt", $buf);
echo "\n明细已写入 tools/baseline/views-{$code}.txt\n";


