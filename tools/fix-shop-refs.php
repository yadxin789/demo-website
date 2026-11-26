<?php

declare(strict_types=1);

/*
 * fix-shop-refs.php — 一次性修正 Shop 模块里的三类跨产品引用
 *
 * import-shop.ps1 只搬了"看得见的路径"，运行时还有三类隐式引用需要加产品前缀：
 *   1) view('auth.login')            -> view('shop::auth.login')
 *   2) route('dashboard') / url('/x') -> route('shop.dashboard') / url('shop/x')
 *   3) asset('css/app.css')           -> asset('assets/shop/css/app.css')
 *
 * 只处理字符串字面量；已带 shop:: / shop. 的一律跳过（负向断言），可重复执行。
 * 用法： php tools\fix-shop-refs.php [--check]
 */

$root  = dirname(__DIR__);
$check = in_array('--check', $argv ?? [], true);
$base  = $root . '/app/Modules/Shop';

$stats = ['view' => 0, 'route' => 0, 'redirect' => 0, 'url' => 0, 'asset' => 0];

function fix_view(string $code): array
{
    $n = 0;
    $code = preg_replace_callback(
        "/\\bview\\(\\s*'([^']+)'/",
        function ($m) use (&$n) {
            if (str_contains($m[1], '::')) {
                return $m[0];
            }
            $n++;
            return "view('shop::" . $m[1] . "'";
        },
        $code
    );
    $code = preg_replace_callback(
        '/\\bview\\(\\s*"([^"]+)"/',
        function ($m) use (&$n) {
            if (str_contains($m[1], '::')) {
                return $m[0];
            }
            $n++;
            return 'view("shop::' . $m[1] . '"';
        },
        $code
    );
    $code = preg_replace_callback(
        "/View::make\\(\\s*'([^']+)'/",
        function ($m) use (&$n) {
            if (str_contains($m[1], '::')) {
                return $m[0];
            }
            $n++;
            return "View::make('shop::" . $m[1] . "'";
        },
        $code
    );

    return [$code, $n];
}

function fix_route(string $code): array
{
    $n = 0;
    $code = preg_replace_callback(
        "/\\broute\\(\\s*'([A-Za-z0-9_.\\-]+)'/",
        function ($m) use (&$n) {
            if (str_starts_with($m[1], 'shop.') || str_starts_with($m[1], 'portal.')) {
                return $m[0];
            }
            $n++;
            return "route('shop." . $m[1] . "'";
        },
        $code
    );
    $code = preg_replace_callback(
        '/\\broute\\(\\s*"([A-Za-z0-9_.\\-]+)"/',
        function ($m) use (&$n) {
            if (str_starts_with($m[1], 'shop.') || str_starts_with($m[1], 'portal.')) {
                return $m[0];
            }
            $n++;
            return 'route("shop.' . $m[1] . '"';
        },
        $code
    );

    return [$code, $n];
}

function fix_redirect_url(string $code): array
{
    $n = 0;
    // redirect('/x') 只处理以 / 开头的路径字面量
    $code = preg_replace_callback(
        "/\\bredirect\\(\\s*'(\\/[^']*)'/",
        function ($m) use (&$n) {
            if (str_starts_with($m[1], '/shop/') || $m[1] === '/shop') {
                return $m[0];
            }
            $n++;
            return "redirect('shop" . $m[1] . "'";
        },
        $code
    );
    // url('/x')
    $code = preg_replace_callback(
        "/\\burl\\(\\s*'(\\/[^']*)'/",
        function ($m) use (&$n) {
            if (str_starts_with($m[1], '/shop/') || $m[1] === '/shop') {
                return $m[0];
            }
            $n++;
            return "url('shop" . $m[1] . "'";
        },
        $code
    );

    return [$code, $n];
}

function fix_asset(string $code): array
{
    $n = 0;
    $dirs = 'css|js|avatars|vendor|img|images|fonts|webfonts|build';
    $code = preg_replace_callback(
        "/\\basset\\(\\s*'(" . $dirs . ")\\/([^']*)'/",
        function ($m) use (&$n) {
            if (str_starts_with($m[1] . '/' . $m[2], 'assets/shop/')) {
                return $m[0];
            }
            $n++;
            return "asset('assets/shop/" . $m[1] . '/' . $m[2] . "'";
        },
        $code
    );

    return [$code, $n];
}

$iterate = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
$touched = 0;

foreach ($iterate as $file) {
    if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'blade'], true)) {
        continue;
    }
    // 视图文件扩展名是 .php（xxx.blade.php），getExtension 返回 php，正好覆盖
    $path = $file->getPathname();
    $raw  = file_get_contents($path);
    $new  = $raw;

    [$new, $n1] = fix_view($new);
    [$new, $n2] = fix_route($new);
    [$new, $n3] = fix_redirect_url($new);
    [$new, $n4] = fix_asset($new);

    if ($new !== $raw) {
        $touched++;
        $stats['view'] += $n1;
        $stats['route'] += $n2;
        $stats['redirect'] += $n3;
        $stats['asset'] += $n4;
        if (! $check) {
            file_put_contents($path, $new);
        }
    }
}

fwrite(
    STDOUT,
    sprintf(
        "%s：涉及 %d 个文件；view %d 处、route %d 处、redirect/url %d 处、asset %d 处\n",
        $check ? '待改' : '已改',
        $touched,
        $stats['view'],
        $stats['route'],
        $stats['redirect'],
        $stats['asset']
    )
);
