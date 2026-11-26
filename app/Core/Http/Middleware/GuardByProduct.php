<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Support\Product;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 按 URL 前缀绑定守卫（HC-08 的第三段）。
 *
 * 为什么必须有这一段：config('auth.defaults.guard') 是全局的，
 * 若不按请求改，同一进程处理 /pm 请求时会用 /erp 的登录态 —— 那就等于单点登录了。
 *
 * 同时做一次前缀校验：/erp/* 的路由只允许在 segment(1) === 'erp' 时进入，
 * 防止路由被其他前缀意外命中。
 */
class GuardByProduct
{
    public function handle(Request $request, Closure $next, string $code): Response
    {
        if ($request->segment(1) !== $code) {
            abort(404);
        }

        $product = Product::find($code);

        if ($product === null || ($product['enabled'] ?? false) !== true) {
            abort(404);
        }

        config(['auth.defaults.guard' => $product['guard']]);
        Auth::shouldUse($product['guard']);

        return $next($request);
    }
}