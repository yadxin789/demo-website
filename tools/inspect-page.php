<?php

/**
 * 打印某个路由对应控制器方法的源码片段，用于定位列表页读取的表。
 *
 *   php tools/inspect-page.php erp/announcements erp/todos ...
 */

require 'E:/demo-website/vendor/autoload.php';
$app = require 'E:/demo-website/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$routes = collect(app('router')->getRoutes()->getRoutes());

foreach (array_slice($argv, 1) as $uri) {
    $uri = ltrim($uri, '/');
    $route = $routes->first(fn ($r) => trim($r->uri(), '/') === $uri && in_array('GET', $r->methods(), true));

    if (! $route) {
        echo "### {$uri} → 路由未找到" . PHP_EOL;
        continue;
    }

    $action = $route->getActionName();
    echo "### {$uri} → {$action}" . PHP_EOL;

    if (! str_contains($action, '@')) {
        echo '  （闭包路由）' . PHP_EOL;
        continue;
    }
    [$class, $method] = explode('@', $action);
    if (! class_exists($class)) {
        echo "  （类不存在）" . PHP_EOL;
        continue;
    }
    $ref = new ReflectionMethod($class, $method);
    $file = $ref->getFileName();
    $lines = file($file);
    $start = $ref->getStartLine();
    $end = min($ref->getEndLine(), $start + 45);
    for ($i = $start; $i <= $end; $i++) {
        echo '  ' . rtrim($lines[$i - 1]) . PHP_EOL;
    }
    echo PHP_EOL;
}
