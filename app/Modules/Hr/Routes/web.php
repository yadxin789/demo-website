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

use App\Modules\Hr\Http\Controllers\AttendanceController;
use App\Modules\Hr\Http\Controllers\Auth\LoginController;
use App\Modules\Hr\Http\Controllers\ContractController;
use App\Modules\Hr\Http\Controllers\DashboardController;
use App\Modules\Hr\Http\Controllers\EmployeeController;
use App\Modules\Hr\Http\Controllers\LeaveController;
use App\Modules\Hr\Http\Controllers\MovementController;
use App\Modules\Hr\Http\Controllers\Org\DepartmentController;
use App\Modules\Hr\Http\Controllers\Org\HeadcountController;
use App\Modules\Hr\Http\Controllers\Org\PositionController;
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
            Route::get('org/departments', [DepartmentController::class, 'index'])->name('org.departments.index');
            Route::post('org/departments', [DepartmentController::class, 'store'])->name('org.departments.store');
            Route::put('org/departments/{department}', [DepartmentController::class, 'update'])
                ->whereNumber('department')->name('org.departments.update');
            Route::delete('org/departments/{department}', [DepartmentController::class, 'destroy'])
                ->whereNumber('department')->name('org.departments.destroy');

            Route::get('org/positions', [PositionController::class, 'index'])->name('org.positions.index');
            Route::post('org/positions', [PositionController::class, 'store'])->name('org.positions.store');
            Route::put('org/positions/{position}', [PositionController::class, 'update'])
                ->whereNumber('position')->name('org.positions.update');

            Route::get('org/headcounts', [HeadcountController::class, 'index'])->name('org.headcounts.index');
            Route::post('org/headcounts', [HeadcountController::class, 'store'])->name('org.headcounts.store');
            Route::put('org/headcounts/{headcount}', [HeadcountController::class, 'update'])
                ->whereNumber('headcount')->name('org.headcounts.update');

            // H2 员工与异动
            Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
            Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::get('employees/contracts', [ContractController::class, 'index'])->name('employees.contracts');
            Route::post('employees/contracts', [ContractController::class, 'store'])->name('employees.contracts.store');
            Route::get('employees/movements', [MovementController::class, 'index'])->name('employees.movements');
            Route::post('employees/movements', [MovementController::class, 'store'])->name('employees.movements.store');
            Route::get('employees/{employee}', [EmployeeController::class, 'show'])
                ->whereNumber('employee')->name('employees.show');
            Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])
                ->whereNumber('employee')->name('employees.edit');
            Route::put('employees/{employee}', [EmployeeController::class, 'update'])
                ->whereNumber('employee')->name('employees.update');
            Route::post('employees/{employee}/regularize', [EmployeeController::class, 'regularize'])
                ->whereNumber('employee')->name('employees.regularize');
            Route::post('employees/{employee}/leave', [EmployeeController::class, 'leave'])
                ->whereNumber('employee')->name('employees.leave');

            // H3 考勤与假期
            Route::get('attendance/shifts', [AttendanceController::class, 'shifts'])->name('attendance.shifts');
            Route::post('attendance/shifts', [AttendanceController::class, 'shiftStore'])->name('attendance.shifts.store');
            Route::put('attendance/shifts/{shift}', [AttendanceController::class, 'shiftUpdate'])
                ->whereNumber('shift')->name('attendance.shifts.update');

            Route::get('attendance/records', [AttendanceController::class, 'records'])->name('attendance.records');

            Route::get('attendance/exceptions', [AttendanceController::class, 'exceptions'])->name('attendance.exceptions');
            Route::post('attendance/exceptions/{exception}/handle', [AttendanceController::class, 'exceptionHandle'])
                ->whereNumber('exception')->name('attendance.exceptions.handle');

            Route::get('attendance/overtimes', [AttendanceController::class, 'overtimes'])->name('attendance.overtimes');
            Route::post('attendance/overtimes', [AttendanceController::class, 'overtimeStore'])->name('attendance.overtimes.store');
            Route::post('attendance/overtimes/{overtime}/approve', [AttendanceController::class, 'overtimeApprove'])
                ->whereNumber('overtime')->name('attendance.overtimes.approve');
            Route::post('attendance/overtimes/{overtime}/reject', [AttendanceController::class, 'overtimeReject'])
                ->whereNumber('overtime')->name('attendance.overtimes.reject');

            Route::get('leaves', [LeaveController::class, 'index'])->name('leaves.index');
            Route::get('leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
            Route::post('leaves', [LeaveController::class, 'store'])->name('leaves.store');
            Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])
                ->whereNumber('leave')->name('leaves.approve');
            Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])
                ->whereNumber('leave')->name('leaves.reject');
            Route::get('leaves/balances', [LeaveController::class, 'balances'])->name('leaves.balances');

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
