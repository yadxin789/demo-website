<?php
/**
 * 直接以指定用户身份调用某控制器方法，暴露真实异常（绕过自定义 500 页）
 * 用法: php tools/probe-route.php erp/rnd/projects 30
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$uri = $argv[1] ?? 'erp/rnd/projects';
$userId = (int) ($argv[2] ?? 30);

$guard = $argv[4] ?? null;
if ($guard) {
    // 像路由中间件 auth:xxx 那样切换默认守卫，否则 auth()->user() 会落到 erp 用户
    Illuminate\Support\Facades\Auth::shouldUse($guard);
    Illuminate\Support\Facades\Auth::guard($guard)->loginUsingId($userId);
} else {
    Illuminate\Support\Facades\Auth::loginUsingId($userId);
}
// CLI 下没有 ShareErrorsFromSession 中间件，手动补上，避免 $errors 未定义假阳性
app('view')->share('errors', new Illuminate\Support\ViewErrorBag());

$routes = app('router')->getRoutes();
$matched = null;
foreach ($routes as $r) {
    if (ltrim($r->uri(), '/') === ltrim($uri, '/') && in_array('GET', $r->methods())) {
        $matched = $r;
        break;
    }
}
if (!$matched) { echo "路由未找到: $uri\n"; exit(1); }

$action = $matched->getActionName();
echo "路由 $uri → $action\n";

$request = Illuminate\Http\Request::create('/' . ltrim($uri, '/'), 'GET');
$request->setLaravelSession(app('session.store'));

try {
    [$class, $method] = array_pad(explode('@', $action), 2, '__invoke');
    $controller = app($class);
    $response = app()->call([$controller, $method], ['request' => $request]);
    $content = is_object($response) && method_exists($response, 'getContent') ? $response->getContent() : (string) $response;
    echo "OK LEN " . strlen($content) . "\n";
    if (!empty($argv[3])) {
        $dir = __DIR__ . '/../storage/logs/probe';
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }
        $file = $dir . '/' . preg_replace('/[^a-z0-9]+/i', '_', $uri) . '.html';
        file_put_contents($file, $content);
        echo "HTML " . realpath($file) . "\n";
        $emptyWords = ['暂无数据', '暂无记录', '暂无', '没有数据', '空'];
        $hits = [];
        foreach ($emptyWords as $w) {
            $n = substr_count($content, $w);
            if ($n) { $hits[$w] = $n; }
        }
        echo "TD " . substr_count($content, '<td') . "  空态词 " . json_encode($hits, JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Throwable $e) {
    echo "EXCEPTION: " . get_class($e) . "\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    $frames = array_slice(explode("\n", $e->getTraceAsString()), 0, 12);
    echo implode("\n", $frames) . "\n";
}
