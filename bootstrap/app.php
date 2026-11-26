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

        // ERP 的 OnlyOffice 保存回调是服务器对服务器请求，不带 CSRF 会话（源项目同款豁免）
        $middleware->validateCsrfTokens(except: [
            'erp/rnd/collab/callback/*',
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

        // 403：按产品渲染各自的岗位拦截页（HC-24）。产品没有自己的 403 页就回门户列表，
        // 不能让客户看到 Laravel 默认的英文报错页。
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException|\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, Request $request) {
            $code = $request->segment(1);

            if ($request->expectsJson()) {
                return response()->json(['message' => '该页面不属于您的岗位'], 403);
            }

            if ($code !== null && Product::isEnabled($code)) {
                $errView = $code . '::errors.403';

                if (view()->exists($errView)) {
                    return response()->view($errView, [
                        'currentUser' => $request->user()?->name,
                        'positionName' => null,
                        'requiredPermission' => $e->getMessage(),
                    ], 403);
                }
            }

            return response()->view('portal::errors.404', [], 403);
        });

        // 门户统一的 404 页：客户点到一个不存在的产品或被关闭的产品时，
        // 不要看到 Laravel 默认报错页，而是回到"产品列表"（HC-30 的体验层）
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            if ($response->getStatusCode() === 404 && ! $request->expectsJson()) {
                return response()->view('portal::errors.404', [], 404);
            }

            return $response;
        });

    })->create();