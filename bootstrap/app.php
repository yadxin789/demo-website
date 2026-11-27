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
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException|\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException|\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                && ! $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
                && $e->getStatusCode() !== 403) {
                return null;
            }

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

        // POS 业务校验失败统一回 422（前端可直接展示 message），页面提交则回跳并带错误
        $exceptions->render(function (\App\Modules\Shop\Exceptions\PosException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['pos' => $e->getMessage()]);
        });

        // HC-24：错误页按产品分流 —— 每个产品带自己的 403/404/500，缺了才回门户，
        // 让客户在任何产品里出错时看到的都是那个产品的牌子，而不是"同一套系统"。
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 404 || $status === 500) {
                $code = $request->segment(1);
                $errView = $code . '::errors.' . $status;

                if ($code !== null && Product::isEnabled($code) && view()->exists($errView)) {
                    try {
                        return response()->view($errView, [], $status);
                    } catch (\Throwable) {
                        // 渲染产品错误页本身失败时，退回默认响应，避免递归渲染
                    }
                }

                if ($status === 404) {
                    return response()->view('portal::errors.404', [], 404);
                }
            }

            return $response;
        });

    })->create();