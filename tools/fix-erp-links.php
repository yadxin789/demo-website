<?php

/**
 * 给 ERP 模块内的 route()/url() 调用补产品前缀。
 * 用法：
 *   php tools/fix-erp-links.php --check   只报告
 *   php tools/fix-erp-links.php           实际改写
 */

$code = 'erp';
$base = __DIR__ . '/../app/Modules/Erp';
$dry = in_array('--check', $argv, true);

$targets = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = $f->getPathname();
    if (str_ends_with($p, '.blade.php') || (str_ends_with($p, '.php') && ! str_contains($p, DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR))) {
        $targets[] = $p;
    }
}

$totalRoute = 0;
$totalUrl = 0;
$changedFiles = 0;
$urlSamples = [];

foreach ($targets as $p) {
    $raw = file_get_contents($p);
    $new = $raw;

    $new = preg_replace("/route\\(\\s*'((?!erp\\.)[A-Za-z0-9_.\\-]+)'/", "route('erp.\$1'", $new, -1, $c1);
    $new = preg_replace('/route\\(\\s*"((?!erp\.)[A-Za-z0-9_.\\-]+)"/', 'route("erp.$1"', $new, -1, $c2);
    $totalRoute += $c1 + $c2;

    // url('xxx') 只在 dry 模式收集，实际改写留给人工确认（数量少但语义各异）
    if (preg_match_all("/url\\('([^']*)'\\)/", $new, $m)) {
        foreach ($m[1] as $seg) {
            $urlSamples[] = str_replace(dirname(__DIR__), '', $p) . ' -> url(\'' . $seg . '\')';
        }
    }

    if ($new !== $raw) {
        $changedFiles++;
        if (! $dry) {
            file_put_contents($p, $new);
        }
    }
}

foreach ($targets as $p) {
    if (str_contains($p, 'views')) {
        continue;
    }
}
echo '扫描文件: ' . count($targets) . "，route() 常量名 $totalRoute 处，改动文件 $changedFiles 个" . ($dry ? '（dry-run）' : '') . "\n";
echo "url() 调用清单：\n";
foreach ($urlSamples as $s) {
    echo '  ' . $s . "\n";
}
