<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 电子制造 ERP 路由
|--------------------------------------------------------------------------
| HC-07：必须同时具备 prefix('erp') 与 name('erp.')
| HC-08：中间件链固定为 web -> module:erp -> product.only:erp
|
| S0 只装占位路由验证"壳通了"；真实业务路由由 S1/S2/S3 从原项目搬入，
| 搬入时只加前缀、不改后缀。
|
*/

Route::middleware(['web', 'module:erp', 'product.only:erp'])
    ->prefix('erp')
    ->name('erp.')
    ->group(function () {

        Route::get('/', function () {
            return auth('erp')->check()
                ? redirect()->route('erp.dashboard')
                : redirect()->route('erp.preview');
        })->name('root');


        Route::get('login', fn () => view('erp::auth.login'))->name('login');

        Route::get('dashboard', fn () => view('erp::dashboard.index'))->name('dashboard');

    });