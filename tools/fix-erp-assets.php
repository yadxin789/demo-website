<?php

/**
 * 给 ERP 视图里的 asset() 本地资源路径加 assets/erp/ 前缀。
 * 只处理 css/js/webfonts/fonts/avatars/img/images 这类相对目录。
 *
 * 用法：php tools/fix-erp-assets.php [--check]
 */

$base = __DIR__ . '/../app/Modules/Erp/Resources/views';
$dry = in_array('--check', $argv, true);

$total = 0;
$changed = 0;
$kinds = [];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = $f->getPathname();
    if (! str_ends_with($p, '.blade.php')) {
        continue;
    }

    $raw = file_get_contents($p);
    $new = preg_replace(
        "/asset\\(\\s*'((?!assets\\/)(?:css|js|webfonts|fonts|avatars|img|images)\\/[^']*)'/",
        "asset('assets/erp/\$1'",
        $raw,
        -1,
        $c1
    );
    $new = preg_replace(
        '/asset\\(\\s*"((?!assets\\/)(?:css|js|webfonts|fonts|avatars|img|images)\\/[^"]*)"/',
        'asset("assets/erp/$1"',
        $new,
        -1,
        $c2
    );

    $total += $c1 + $c2;
    if ($new !== $raw) {
        $changed++;
        preg_match_all("/asset\\(\\s*'(assets\\/erp\\/[^']*)'/", $new, $m);
        foreach ($m[1] as $s) {
            $kinds[$s] = ($kinds[$s] ?? 0) + 1;
        }
        if (! $dry) {
            file_put_contents($p, $new);
        }
    }
}

echo 'asset() 改写 ' . $total . ' 处，涉及 ' . $changed . ' 个视图文件' . ($dry ? '（dry-run）' : '') . "\n";
foreach (array_slice($kinds, 0, 8, true) as $k => $v) {
    echo '  ' . $k . ' ×' . $v . "\n";
}
