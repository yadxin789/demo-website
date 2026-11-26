<?php

declare(strict_types=1);

/*
 * fix-shop-namespace.php — 补齐命名空间声明
 *
 * import-shop.ps1 的 nsMap 只替换带尾随反斜杠的引用（App\Models\），
 * 文件头部的 `namespace App\Models;` 这类声明不带尾随反斜杠，会漏掉。
 * 本脚本把 App\Modules\Shop 下所有 `namespace App\Xxx;` 补成 `namespace App\Modules\Shop\Xxx;`。
 *
 * 用法： php tools\fix-shop-namespace.php [--check]
 */

$root  = dirname(__DIR__);
$check = in_array('--check', $argv ?? [], true);
$base  = $root . '/app/Modules/Shop';

$iterate = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
$fixed = 0;
$total = 0;

foreach ($iterate as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $total++;
    $path = $file->getPathname();
    $raw  = file_get_contents($path);

    if (preg_match('/^namespace App\\\\(?!Modules\\\\)([A-Za-z\\\\]+);/m', $raw, $m)) {
        $new = preg_replace(
            '/^namespace App\\\\(?!Modules\\\\)([A-Za-z\\\\]+);/m',
            'namespace App\\\\Modules\\\\Shop\\\\$1;',
            $raw
        );
        if ($new !== $raw) {
            $fixed++;
            if ($check) {
                fwrite(STDOUT, "  [check] {$path}\n");
            } else {
                file_put_contents($path, $new);
            }
        }
    }
}

fwrite(STDOUT, ($check ? '待修' : '已修') . " {$fixed} / {$total} 个文件的 namespace 声明\n");
