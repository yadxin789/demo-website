<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MES 模块路由（产品前缀 /mes）
|--------------------------------------------------------------------------
| 全部路由挂在 /mes 前缀与 mes. 名字空间下，与其它四个产品零交叉（HC-06/07/08）。
| 中间件链固定：web → module:mes（切容器/连接/会话上下文）→ product.only:mes（enabled 校验）。
|
| S5-M0 阶段：登录 + 工作台真实可用，其余页面先由 PlaceholderController 承接，
| 随 M1~M6 逐步替换为真实控制器，保证期间导航不出现 404。
*/

use App\Modules\Mes\Http\Controllers\Auth\LoginController;
use App\Modules\Mes\Http\Controllers\DashboardController;
use App\Modules\Mes\Http\Controllers\MasterData\MaterialController;
use App\Modules\Mes\Http\Controllers\MasterData\ShiftController;
use App\Modules\Mes\Http\Controllers\MasterData\WorkCenterController;
use App\Modules\Mes\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'module:mes', 'product.only:mes'])
    ->prefix('mes')
    ->name('mes.')
    ->group(function () {

        // 产品根：已登录进工作台，否则进登录页
        Route::get('/', function () {
            return auth('mes')->check()
                ? redirect()->route('mes.dashboard')
                : redirect()->route('mes.login');
        })->name('root');

        // 独立登录（已登录时由控制器自行跳回工作台）
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->name('login.submit');
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');

        Route::middleware('auth:mes')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

            // 现场
            Route::get('kanban', [PlaceholderController::class, 'show'])->name('kanban');

            // 工单（M2 替换）
            Route::get('work-orders', [PlaceholderController::class, 'show'])->name('work-orders.index');
            Route::get('work-orders/{workOrder}', [PlaceholderController::class, 'show'])
                ->whereNumber('workOrder')
                ->name('work-orders.show');

            // 主数据（M1：物料已真实落地，其余逐步替换）
            Route::prefix('master')->name('master.')->group(function () {
                Route::get('materials', [MaterialController::class, 'index'])->name('materials.index');
                Route::post('materials', [MaterialController::class, 'store'])->name('materials.store');
                Route::put('materials/{material}', [MaterialController::class, 'update'])
                    ->whereNumber('material')
                    ->name('materials.update');

                Route::get('boms', [PlaceholderController::class, 'show'])->name('boms.index');
                Route::get('routings', [PlaceholderController::class, 'show'])->name('routings.index');

                Route::get('work-centers', [WorkCenterController::class, 'index'])->name('work-centers.index');
                Route::post('work-centers', [WorkCenterController::class, 'store'])->name('work-centers.store');
                Route::put('work-centers/{workCenter}', [WorkCenterController::class, 'update'])
                    ->whereNumber('workCenter')
                    ->name('work-centers.update');

                Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
                Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
                Route::put('shifts/{shift}', [ShiftController::class, 'update'])
                    ->whereNumber('shift')
                    ->name('shifts.update');
            });

            // 质量（M4 替换）
            Route::get('quality/inspections', [PlaceholderController::class, 'show'])->name('quality.inspections.index');

            // 设备（M5 替换）
            Route::get('equipment', [PlaceholderController::class, 'show'])->name('equipment.index');

            // 导入（M1 替换）
            Route::get('import', [PlaceholderController::class, 'show'])->name('import.index');

            // 系统（M6 替换）
            Route::get('system/users', [PlaceholderController::class, 'show'])->name('system.users.index');
        });
    });
