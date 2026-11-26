<?php

declare(strict_types=1);

use App\Core\Http\Middleware\GuardByProduct;
use App\Core\Http\Middleware\SetProductContext;
use App\Core\Support\Product;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // HC-08：产品中间件别名，模块路由统一用 module:{code} / product.only:{code}
        $middleware->alias([
            'module' => SetProductContext::class,
            'product.only' => GuardByProduct::class,
        ]);

        // 未登录访问受保护页面时，按 URL 前缀分流到各产品自己的登录页（HC-12）
        // 注意：这里返回的是"登录页"，不是预览页 —— 预览页是主动点击才进的
        $middleware->redirectGuestsTo(function (Request $request): string {
            $code = $request->segment(1);

            if ($code !== null && Product::isEnabled($code)) {
                return route($code . '.login');
            }

            return '/';
        });

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // 门户统一的 404 页：客户点到一个不存在的产品或被关闭的产品时，
        // 不要看到 Laravel 默认报错页，而是回到"产品列表"（HC-30 的体验层）
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            if ($response->getStatusCode() === 404 && ! $request->expectsJson()) {
                return response()->view('portal::errors.404', [], 404);
            }

            return $response;
        });

    })->create();