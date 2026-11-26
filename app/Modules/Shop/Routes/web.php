<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 百货零售 ERP 路由
|--------------------------------------------------------------------------
| HC-07：必须同时具备 prefix('shop') 与 name('shop.')
| HC-08：中间件链固定为 web -> module:shop -> product.only:shop
|
| S0 只装占位路由验证"壳通了"；真实业务路由由 S1/S2/S3 从原项目搬入，
| 搬入时只加前缀、不改后缀。
|
*/

Route::middleware(['web', 'module:shop', 'product.only:shop'])
    ->prefix('shop')
    ->name('shop.')
    ->group(function () {

        Route::get('/', function () {
            return auth('shop')->check()
                ? redirect()->route('shop.dashboard')
                : redirect()->route('shop.preview');
        })->name('root');


        Route::get('login', fn () => view('shop::auth.login'))->name('login');

        Route::get('dashboard', fn () => view('shop::dashboard.index'))->name('dashboard');

    });