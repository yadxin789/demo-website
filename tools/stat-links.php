<?php

/**
 * 统计 ERP 模块里各类链接调用方式，供搬迁时判断需要加多少前缀。
 * 用法：php tools/stat-links.php erp
 */

$code = $argv[1] ?? 'erp';
$base = __DIR__ . '/../app/Modules/' . ucfirst($code);
$roots = [
    'views' => $base . '/Resources/views',
    'code'  => $base,
];

foreach ($roots as $label => $dir) {
    if (! is_dir($dir)) {
        echo "$label: 目录不存在\n";
        continue;
    }

    $files = 0;
    $route = 0;
    $routeDq = 0;
    $url = 0;
    $hrefAbs = 0;

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        $name = $f->getFilename();
        if ($label === 'views' && ! str_ends_with($name, '.blade.php')) {
            continue;
        }
        if ($label === 'code' && ! str_ends_with($name, '.php')) {
            continue;
        }
        if ($label === 'code' && str_contains($f->getPathname(), DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $files++;
        $raw = file_get_contents($f->getPathname());
        $route += preg_match_all("/route\\('/", $raw);
        $routeDq += preg_match_all('/route\\("/', $raw);
        $url += preg_match_all("/url\\('/", $raw);
        $hrefAbs += preg_match_all('/href="\\//', $raw);
    }

    echo str_pad($label, 6) . " 文件=$files  route('=$route  route(\"=$routeDq  url('=$url  href绝对=$hrefAbs\n";
}
