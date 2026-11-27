<?php

declare(strict_types=1);

use App\Core\Http\Controllers\LeadController;
use App\Core\Http\Controllers\PortalController;
use App\Core\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 门户路由（唯一不带产品前缀的路由组）
|--------------------------------------------------------------------------
| HC-06：门户路由名一律 portal. 或 {product}.preview 前缀
| HC-30：enabled=false 的产品不注册预览路由 —— URL 自然 404，而不是靠页面判断
|
*/

Route::get('/', [PortalController::class, 'home'])->name('portal.home');

// 线索捕获（S7-12）：只写 portal.sqlite 的 leads 表
Route::post('lead', [LeadController::class, 'store'])
    ->middleware('throttle:5,10')
    ->name('portal.lead.store');

// 关于与合规（S7-13）：备案信息、隐私说明、联系方式
Route::get('about', [PortalController::class, 'about'])->name('portal.about');

foreach (config('products.products', []) as $code => $product) {
    if (($product['enabled'] ?? false) !== true) {
        continue;
    }

    Route::get($code . '/preview', [PreviewController::class, 'show'])
        ->defaults('product', $code)
        ->name($code . '.preview');
}