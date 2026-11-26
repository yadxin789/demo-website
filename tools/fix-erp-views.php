<?php

/**
 * 给 ERP 模块 PHP 代码里的 view()/View::make() 调用补 erp:: 命名空间前缀。
 * 视图文件本身已由 import-erp.ps1 处理过，这里只扫代码。
 *
 * 用法：php tools/fix-erp-views.php [--check]
 */

$base = __DIR__ . '/../app/Modules/Erp';
$dry = in_array('--check', $argv, true);

$total = 0;
$changed = 0;
$samples = [];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = $f->getPathname();
    if (! str_ends_with($p, '.php')) {
        continue;
    }
    if (str_contains($p, DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $raw = file_get_contents($p);
    $new = $raw;

    $new = preg_replace("/view\\(\\s*'((?!erp::)[A-Za-z0-9_.\\-]+)'/", "view('erp::\$1'", $new, -1, $c1);
    $new = preg_replace('/view\\(\\s*"((?!erp::)[A-Za-z0-9_.\\-]+)"/', 'view("erp::$1"', $new, -1, $c2);
    $new = preg_replace("/View::make\\(\\s*'((?!erp::)[A-Za-z0-9_.\\-]+)'/", "View::make('erp::\$1'", $new, -1, $c3);
    $new = preg_replace("/(?:response\\(\\)->)?view\\(\\s*'((?!erp::)[A-Za-z0-9_.\\-]+)'/", "view('erp::\$1'", $new, -1, $c4);

    $n = $c1 + $c2 + $c3;
    $total += $n;

    if ($new !== $raw) {
        $changed++;
        if (count($samples) < 8) {
            preg_match_all("/view\\(\\s*'(?!erp::)([A-Za-z0-9_.\\-]+)'/", $new, $m);
            foreach ($m[1] as $s) {
                $samples[] = str_replace(__DIR__ . '/../', '', $p) . ' -> ' . $s;
            }
        }
        if (! $dry) {
            file_put_contents($p, $new);
        }
    }
}

echo 'view()/View::make() 改写 ' . $total . ' 处，涉及 ' . $changed . ' 个文件' . ($dry ? '（dry-run）' : '') . "\n";
foreach ($samples as $s) {
    echo '  ' . $s . "\n";
}
