<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 人事管理 HR 模块路由（产品前缀 /hr）
|--------------------------------------------------------------------------
| 全部路由挂在 /hr 前缀与 hr. 名字空间下，与其它四个产品零交叉（HC-06/07/08）。
| 中间件链固定：web → module:hr（切容器/连接/会话上下文）→ product.only:hr（enabled 校验）。
|
| S6-H0 阶段：登录 + 看板真实可用，其余页面先由 PlaceholderController 承接，
| 随 H1~H6 逐步替换为真实控制器，保证期间导航不出现 404。
*/

use App\Modules\Hr\Http\Controllers\Auth\LoginController;
use App\Modules\Hr\Http\Controllers\DashboardController;
use App\Modules\Hr\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'module:hr', 'product.only:hr'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {

        // 产品根：已登录进工作台，否则进登录页
        Route::get('/', function () {
            return auth('hr')->check()
                ? redirect()->route('hr.dashboard')
                : redirect()->route('hr.login');
        })->name('root');

        // 独立登录（已登录时由控制器自行跳回工作台）
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->name('login.submit');
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');

        Route::middleware('auth:hr')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

            // H1 组织与编制
            Route::get('org/departments', [PlaceholderController::class, 'show'])->name('org.departments.index');
            Route::get('org/positions', [PlaceholderController::class, 'show'])->name('org.positions.index');
            Route::get('org/headcounts', [PlaceholderController::class, 'show'])->name('org.headcounts.index');

            // H2 员工与异动
            Route::get('employees', [PlaceholderController::class, 'show'])->name('employees.index');
            Route::get('employees/contracts', [PlaceholderController::class, 'show'])->name('employees.contracts');
            Route::get('employees/movements', [PlaceholderController::class, 'show'])->name('employees.movements');

            // H3 考勤与假期
            Route::get('attendance/shifts', [PlaceholderController::class, 'show'])->name('attendance.shifts');
            Route::get('attendance/records', [PlaceholderController::class, 'show'])->name('attendance.records');
            Route::get('attendance/exceptions', [PlaceholderController::class, 'show'])->name('attendance.exceptions');
            Route::get('attendance/overtimes', [PlaceholderController::class, 'show'])->name('attendance.overtimes');
            Route::get('leaves', [PlaceholderController::class, 'show'])->name('leaves.index');
            Route::get('leaves/balances', [PlaceholderController::class, 'show'])->name('leaves.balances');

            // H4 薪资
            Route::get('payroll/structures', [PlaceholderController::class, 'show'])->name('payroll.structures');
            Route::get('payroll/runs', [PlaceholderController::class, 'show'])->name('payroll.runs.index');
            Route::get('payroll/my', [PlaceholderController::class, 'show'])->name('payroll.my');

            // H5 招聘与绩效
            Route::get('recruit/requisitions', [PlaceholderController::class, 'show'])->name('recruit.requisitions');
            Route::get('recruit/candidates', [PlaceholderController::class, 'show'])->name('recruit.candidates');
            Route::get('recruit/interviews', [PlaceholderController::class, 'show'])->name('recruit.interviews');
            Route::get('performance/cycles', [PlaceholderController::class, 'show'])->name('performance.cycles');
            Route::get('performance/reviews', [PlaceholderController::class, 'show'])->name('performance.reviews');
            Route::get('performance/results', [PlaceholderController::class, 'show'])->name('performance.results');

            // H6 报表与系统
            Route::get('reports/headcount', [PlaceholderController::class, 'show'])->name('reports.headcount');
            Route::get('reports/attendance', [PlaceholderController::class, 'show'])->name('reports.attendance');
            Route::get('reports/payroll', [PlaceholderController::class, 'show'])->name('reports.payroll');
            Route::get('import', [PlaceholderController::class, 'show'])->name('import.index');
            Route::get('system/users', [PlaceholderController::class, 'show'])->name('system.users.index');
            Route::get('system/roles', [PlaceholderController::class, 'show'])->name('system.roles.index');
            Route::get('system/notifications', [PlaceholderController::class, 'show'])->name('system.notifications');
        });
    });
