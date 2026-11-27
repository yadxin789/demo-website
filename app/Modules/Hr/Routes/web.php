<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 人事管理 HR 模块路由（产品前缀 /hr）
|--------------------------------------------------------------------------
| 全部路由挂在 /hr 前缀与 hr. 名字空间下，与其它四个产品零交叉（HC-06/07/08）。
| 中间件链固定：web → module:hr（切容器/连接/会话上下文）→ product.only:hr（enabled 校验）。
|
| H0~H5 各阶段页面均接真实控制器，H6 段已清空（无占位路由、无占位视图）。
*/

use App\Modules\Hr\Http\Controllers\AttendanceController;
use App\Modules\Hr\Http\Controllers\LandingController;
use App\Modules\Hr\Http\Controllers\Auth\LoginController;
use App\Modules\Hr\Http\Controllers\ContractController;
use App\Modules\Hr\Http\Controllers\DashboardController;
use App\Modules\Hr\Http\Controllers\EmployeeController;
use App\Modules\Hr\Http\Controllers\ImportController;
use App\Modules\Hr\Http\Controllers\LeaveController;
use App\Modules\Hr\Http\Controllers\MeController;
use App\Modules\Hr\Http\Controllers\MovementController;
use App\Modules\Hr\Http\Controllers\Org\DepartmentController;
use App\Modules\Hr\Http\Controllers\Org\HeadcountController;
use App\Modules\Hr\Http\Controllers\Org\PositionController;
use App\Modules\Hr\Http\Controllers\PayrollController;
use App\Modules\Hr\Http\Controllers\PerformanceController;
use App\Modules\Hr\Http\Controllers\RecruitmentController;
use App\Modules\Hr\Http\Controllers\ReportController;
use App\Modules\Hr\Http\Controllers\RosterController;
use App\Modules\Hr\Http\Controllers\SkillController;
use App\Modules\Hr\Http\Controllers\SystemController;
use App\Modules\Hr\Http\Controllers\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'module:hr', 'product.only:hr'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {

        // 产品根：已登录进工作台，否则进登录页
        // 产品根（S8）：未登录先看对外首页，已登录进工作台
        Route::get('/', [LandingController::class, 'show'])->name('root');

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
            Route::delete('org/positions/{position}', [PositionController::class, 'destroy'])
                ->whereNumber('position')->name('org.positions.destroy');

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

            // 排班表（M7 增补）
            Route::get('attendance/roster', [RosterController::class, 'index'])->name('attendance.roster');
            Route::post('attendance/roster', [RosterController::class, 'store'])->name('attendance.roster.store');
            Route::delete('attendance/roster/{assignment}', [RosterController::class, 'destroy'])
                ->whereNumber('assignment')->name('attendance.roster.destroy');
            Route::post('attendance/roster/{assignment}/mark', [RosterController::class, 'mark'])
                ->whereNumber('assignment')->name('attendance.roster.mark');

            // 培训管理（M7 增补）
            Route::prefix('training')->name('training.')->group(function () {
                Route::get('/', [TrainingController::class, 'index'])->name('index');
                Route::post('courses', [TrainingController::class, 'storeCourse'])->name('courses.store');
                Route::put('courses/{course}', [TrainingController::class, 'updateCourse'])
                    ->whereNumber('course')->name('courses.update');
                Route::post('sessions', [TrainingController::class, 'storeSession'])->name('sessions.store');
                Route::post('sessions/{session}/records', [TrainingController::class, 'storeRecord'])
                    ->whereNumber('session')->name('sessions.records.store');
                Route::put('records/{record}', [TrainingController::class, 'updateRecord'])
                    ->whereNumber('record')->name('records.update');
            });

            // 技能矩阵与证书（M7 增补）
            Route::get('skills', [SkillController::class, 'index'])->name('skills.index');
            Route::post('skills', [SkillController::class, 'storeSkill'])->name('skills.store');
            Route::put('skills/{skill}', [SkillController::class, 'updateSkill'])
                ->whereNumber('skill')->name('skills.update');
            Route::post('skills/assign', [SkillController::class, 'assign'])->name('skills.assign');
            Route::delete('skills/assignments/{assignment}', [SkillController::class, 'destroyAssignment'])
                ->whereNumber('assignment')->name('skills.assignments.destroy');
            Route::get('skills/certificates', [SkillController::class, 'certificates'])->name('skills.certificates');
            Route::post('skills/certificates', [SkillController::class, 'storeCertificate'])->name('skills.certificates.store');
            Route::delete('skills/certificates/{certificate}', [SkillController::class, 'destroyCertificate'])
                ->whereNumber('certificate')->name('skills.certificates.destroy');

            // 员工自助（M7 增补）
            Route::get('me', [MeController::class, 'index'])->name('me');

            // H4 薪资
            Route::get('payroll/structures', [PayrollController::class, 'structures'])->name('payroll.structures');
            Route::post('payroll/structures', [PayrollController::class, 'structureStore'])->name('payroll.structures.store');
            Route::put('payroll/structures/{structure}', [PayrollController::class, 'structureUpdate'])
                ->whereNumber('structure')->name('payroll.structures.update');

            Route::get('payroll/runs', [PayrollController::class, 'runs'])->name('payroll.runs.index');
            Route::post('payroll/runs/generate', [PayrollController::class, 'generate'])->name('payroll.runs.generate');
            Route::get('payroll/runs/{run}', [PayrollController::class, 'runShow'])
                ->whereNumber('run')->name('payroll.runs.show');
            Route::get('payroll/runs/{run}/export', [PayrollController::class, 'runExport'])
                ->whereNumber('run')->name('payroll.runs.export');
            Route::post('payroll/runs/{run}/confirm', [PayrollController::class, 'runConfirm'])
                ->whereNumber('run')->name('payroll.runs.confirm');
            Route::post('payroll/runs/{run}/pay', [PayrollController::class, 'runPay'])
                ->whereNumber('run')->name('payroll.runs.pay');

            Route::get('payroll/my', [PayrollController::class, 'my'])->name('payroll.my');

            // H5 招聘与绩效
            Route::get('recruit/requisitions', [RecruitmentController::class, 'requisitions'])->name('recruit.requisitions');
            Route::post('recruit/requisitions', [RecruitmentController::class, 'requisitionStore'])->name('recruit.requisitions.store');
            Route::post('recruit/requisitions/{requisition}/close', [RecruitmentController::class, 'requisitionClose'])
                ->whereNumber('requisition')->name('recruit.requisitions.close');

            Route::get('recruit/candidates', [RecruitmentController::class, 'candidates'])->name('recruit.candidates');
            Route::post('recruit/candidates', [RecruitmentController::class, 'candidateStore'])->name('recruit.candidates.store');
            Route::post('recruit/candidates/{candidate}/advance', [RecruitmentController::class, 'candidateAdvance'])
                ->whereNumber('candidate')->name('recruit.candidates.advance');
            Route::post('recruit/candidates/{candidate}/reject', [RecruitmentController::class, 'candidateReject'])
                ->whereNumber('candidate')->name('recruit.candidates.reject');

            Route::get('recruit/interviews', [RecruitmentController::class, 'interviews'])->name('recruit.interviews');
            Route::post('recruit/interviews', [RecruitmentController::class, 'interviewStore'])->name('recruit.interviews.store');

            Route::get('performance/cycles', [PerformanceController::class, 'cycles'])->name('performance.cycles');
            Route::post('performance/cycles', [PerformanceController::class, 'cycleStore'])->name('performance.cycles.store');
            Route::post('performance/cycles/{cycle}/indicators', [PerformanceController::class, 'indicatorStore'])
                ->whereNumber('cycle')->name('performance.indicators.store');
            Route::post('performance/cycles/{cycle}/close', [PerformanceController::class, 'cycleClose'])
                ->whereNumber('cycle')->name('performance.cycles.close');

            Route::get('performance/reviews', [PerformanceController::class, 'reviews'])->name('performance.reviews');
            Route::get('performance/reviews/{employee}', [PerformanceController::class, 'reviewForm'])
                ->whereNumber('employee')->name('performance.reviews.form');
            Route::post('performance/reviews/{employee}', [PerformanceController::class, 'reviewSubmit'])
                ->whereNumber('employee')->name('performance.reviews.submit');

            Route::get('performance/results', [PerformanceController::class, 'results'])->name('performance.results');

            // H6 报表与系统
            Route::get('reports/headcount', [ReportController::class, 'headcount'])->name('reports.headcount');
            Route::get('reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
            Route::get('reports/payroll', [ReportController::class, 'payroll'])->name('reports.payroll');

            Route::get('import', [ImportController::class, 'index'])->name('import.index');
            Route::get('import/template/{type}', [ImportController::class, 'template'])->name('import.template');
            Route::post('import/upload/{type}', [ImportController::class, 'upload'])->name('import.upload');
            Route::post('import/confirm', [ImportController::class, 'confirm'])->name('import.confirm');
            Route::post('import/cancel', [ImportController::class, 'cancel'])->name('import.cancel');
            Route::get('import/export/attendance', [ImportController::class, 'exportAttendance'])->name('import.export.attendance');

            Route::get('system/users', [SystemController::class, 'users'])->name('system.users.index');

            // 账号 CRUD（S12-H05）：新建 / 编辑 / 删除，密码由操作者填写，页面不预填
            Route::get('system/users/create', [SystemController::class, 'userCreate'])->name('system.users.create');
            Route::post('system/users', [SystemController::class, 'userStore'])->name('system.users.store');
            Route::get('system/users/{user}/edit', [SystemController::class, 'userEdit'])
                ->whereNumber('user')->name('system.users.edit');
            Route::put('system/users/{user}/profile', [SystemController::class, 'userProfile'])
                ->whereNumber('user')->name('system.users.profile');
            Route::delete('system/users/{user}', [SystemController::class, 'userDestroy'])
                ->whereNumber('user')->name('system.users.destroy');

            Route::post('system/users/{user}/role', [SystemController::class, 'userRole'])
                ->whereNumber('user')->name('system.users.role');
            Route::post('system/users/{user}/toggle', [SystemController::class, 'userToggle'])
                ->whereNumber('user')->name('system.users.toggle');

            Route::get('system/roles', [SystemController::class, 'roles'])->name('system.roles.index');
            Route::post('system/roles/{role}/permissions', [SystemController::class, 'rolePermissions'])
                ->name('system.roles.permissions');

            Route::get('system/notifications', [SystemController::class, 'notifications'])->name('system.notifications');
            Route::post('system/notifications', [SystemController::class, 'notifySend'])->name('system.notifications.send');
            Route::post('system/notifications/read', [SystemController::class, 'notifyRead'])->name('system.notifications.read');
        });
    });

