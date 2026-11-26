<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 研发项目管理 路由
|--------------------------------------------------------------------------
| HC-07：必须同时具备 prefix('pm') 与 name('pm.')
| HC-08：中间件链固定为 web -> module:pm -> product.only:pm
|
| S0 只装占位路由验证"壳通了"；真实业务路由由 S1/S2/S3 从原项目搬入，
| 搬入时只加前缀、不改后缀。
|
*/

Route::middleware(['web', 'module:pm', 'product.only:pm'])
    ->prefix('pm')
    ->name('pm.')
    ->group(function () {

        Route::get('/', function () {
            return auth('pm')->check()
                ? redirect()->route('pm.dashboard')
                : redirect()->route('pm.preview');
        })->name('root');


        Route::get('login', fn () => view('pm::auth.login'))->name('login');

        Route::get('dashboard', fn () => view('pm::dashboard.index'))->name('dashboard');

    });