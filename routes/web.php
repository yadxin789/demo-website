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

    /*
    |----------------------------------------------------------------------
    | 系统管理后台（五产品统一）
    |----------------------------------------------------------------------
    | 路径统一在 /{产品}/admin 下，只做账号与组织管理、导入引导、运维监控、
    | 系统设置，不承载业务单据、也不提供业务入口。
    | 已被各模块自身实现占用的路径（如 ERP 的 departments/positions/ai-guide、
    | PM 的 index/users/departments/roles）不重复注册，沿用原页面但统一换后台皮肤。
    */
    $skip = [
        'erp' => ['departments', 'positions', 'ai-guide'],
        'pm' => ['home', 'users', 'departments', 'roles'],
        'shop' => [],
        'mes' => [],
        'hr' => [],
    ][$code] ?? [];

    // PM 与参考目录 pm-server-openEuler 严格对齐：后台只保留模块自身的
    // /pm/admin（AdminController + layouts.app 皮肤），不再注册统一后台的额外页面。
    if ($code !== 'pm') {
        Route::middleware(['web', 'module:' . $code, 'product.only:' . $code, 'auth:' . $code])
            ->prefix($code . '/admin')
            ->name($code . '.console.')
            ->group(function () use ($code, $skip) {
                $c = \App\Http\Controllers\System\SystemConsoleController::class;

            if (! in_array('home', $skip, true)) {
                Route::get('/', [$c, 'dashboard'])->defaults('product', $code)->name('home');
            }
            if (! in_array('users', $skip, true)) {
                Route::get('users', [$c, 'users'])->defaults('product', $code)->name('users');
            }
            Route::post('users/{id}/status', [$c, 'userToggle'])->whereNumber('id')
                ->defaults('product', $code)->name('users.status');

            if (! in_array('departments', $skip, true)) {
                Route::get('departments', [$c, 'departments'])->defaults('product', $code)->name('departments');
                Route::post('departments', [$c, 'departmentStore'])->defaults('product', $code)->name('departments.store');
                Route::post('departments/{id}', [$c, 'departmentUpdate'])->whereNumber('id')
                    ->defaults('product', $code)->name('departments.update');
            }

            if (! in_array('positions', $skip, true)) {
                Route::get('positions', [$c, 'positions'])->defaults('product', $code)->name('positions');
                Route::post('positions', [$c, 'positionStore'])->defaults('product', $code)->name('positions.store');
                Route::post('positions/{id}', [$c, 'positionUpdate'])->whereNumber('id')
                    ->defaults('product', $code)->name('positions.update');
            }

            if (! in_array('roles', $skip, true)) {
                Route::get('roles', [$c, 'roles'])->defaults('product', $code)->name('roles');
            }
            if (! in_array('ai-guide', $skip, true)) {
                Route::get('ai-guide', [$c, 'aiGuide'])->defaults('product', $code)->name('ai-guide');
            }

            Route::get('ops', [$c, 'ops'])->defaults('product', $code)->name('ops');
                Route::get('settings', [$c, 'settings'])->defaults('product', $code)->name('settings');
            });
    }
}