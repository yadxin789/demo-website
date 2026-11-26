<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Support\Product;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 设置产品上下文（HC-08 的第二段）。
 *
 * 顺序：web -> module:{code} -> product.only:{code}
 * 这里的职责：
 *   1. enabled=false 一律 404（含预览页，HC-30）
 *   2. 记录当前产品
 *   3. 把默认连接切到本产品库 —— 漏写 $connection 的模型会落到正确的库
 *   4. 把默认日志通道切到本产品
 */
class SetProductContext
{
    public function handle(Request $request, Closure $next, string $code): Response
    {
        $product = Product::find($code);

        if ($product === null || ($product['enabled'] ?? false) !== true) {
            abort(404);
        }

        Product::setCurrent($code);

        config(['database.default' => $product['connection']]);
        config(['logging.default' => $code]);

        return $next($request);
    }
}