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

use App\Modules\Mes\Http\Controllers\ApsController;
use App\Modules\Mes\Http\Controllers\Auth\LoginController;
use App\Modules\Mes\Http\Controllers\DashboardController;
use App\Modules\Mes\Http\Controllers\EquipmentController;
use App\Modules\Mes\Http\Controllers\ImportController;
use App\Modules\Mes\Http\Controllers\KanbanController;
use App\Modules\Mes\Http\Controllers\MasterData\BomController;
use App\Modules\Mes\Http\Controllers\MasterData\MaterialController;
use App\Modules\Mes\Http\Controllers\MasterData\RoutingController;
use App\Modules\Mes\Http\Controllers\MasterData\ShiftController;
use App\Modules\Mes\Http\Controllers\MasterData\WorkCenterController;
use App\Modules\Mes\Http\Controllers\MaterialIssueController;
use App\Modules\Mes\Http\Controllers\MyTaskController;
use App\Modules\Mes\Http\Controllers\Quality\DefectController;
use App\Modules\Mes\Http\Controllers\Quality\InspectionController;
use App\Modules\Mes\Http\Controllers\Quality\InspectionTemplateController;
use App\Modules\Mes\Http\Controllers\PlaceholderController;
use App\Modules\Mes\Http\Controllers\ReportController;
use App\Modules\Mes\Http\Controllers\Reports\LaborController;
use App\Modules\Mes\Http\Controllers\Reports\OutputController;
use App\Modules\Mes\Http\Controllers\Reports\YieldController;
use App\Modules\Mes\Http\Controllers\System\NotificationController as SystemNotificationController;
use App\Modules\Mes\Http\Controllers\System\RoleController as SystemRoleController;
use App\Modules\Mes\Http\Controllers\System\UserController as SystemUserController;
use App\Modules\Mes\Http\Controllers\WorkOrderController;
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
            Route::get('kanban', [KanbanController::class, 'index'])->name('kanban');

            // 我的任务（M3-07，操作工视角；报工页移动优先）
            Route::get('my-tasks', [MyTaskController::class, 'index'])->name('my-tasks.index');
            Route::get('my-tasks/{operation}', [MyTaskController::class, 'report'])
                ->whereNumber('operation')->name('my-tasks.report');

            // 工单（M2：列表 / 详情 / 建单 / 下达 / 派工）
            Route::get('work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
            Route::post('work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
            Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])
                ->whereNumber('workOrder')->name('work-orders.show');
            Route::put('work-orders/{workOrder}', [WorkOrderController::class, 'update'])
                ->whereNumber('workOrder')->name('work-orders.update');
            Route::post('work-orders/{workOrder}/release', [WorkOrderController::class, 'release'])
                ->whereNumber('workOrder')->name('work-orders.release');
            Route::post('work-orders/{workOrder}/dispatch-all', [WorkOrderController::class, 'dispatchAll'])
                ->whereNumber('workOrder')->name('work-orders.dispatch-all');
            Route::post('work-orders/{workOrder}/operations/{operation}/dispatch', [WorkOrderController::class, 'dispatch'])
                ->whereNumber('workOrder')->whereNumber('operation')->name('work-orders.operations.dispatch');
            Route::post('work-orders/{workOrder}/operations/{operation}/report', [WorkOrderController::class, 'report'])
                ->whereNumber('workOrder')->whereNumber('operation')->name('work-orders.operations.report');
            Route::post('work-orders/{workOrder}/issues', [MaterialIssueController::class, 'store'])
                ->whereNumber('workOrder')->name('work-orders.issues.store');

            // 领料发料（M3）
            Route::get('issues', [MaterialIssueController::class, 'index'])->name('issues.index');
            Route::get('issues/{issue}', [MaterialIssueController::class, 'show'])
                ->whereNumber('issue')->name('issues.show');
            Route::post('issues/{issue}/issue', [MaterialIssueController::class, 'issue'])
                ->whereNumber('issue')->name('issues.issue');

            // 排产（M2-08 / M2-09）：甘特图 + 一键重排
            Route::get('aps', [ApsController::class, 'index'])->name('aps.index');
            Route::post('aps/schedule', [ApsController::class, 'schedule'])->name('aps.schedule');

            // 报工流水（M3）
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

            // 报表（M6-03）：产量趋势 / 良率分析 / 工时效率
            Route::get('reports/output', [OutputController::class, 'index'])->name('reports.output');
            Route::get('reports/yield', [YieldController::class, 'index'])->name('reports.yield');
            Route::get('reports/labor', [LaborController::class, 'index'])->name('reports.labor');

            // 主数据（M1：物料已真实落地，其余逐步替换）
            Route::prefix('master')->name('master.')->group(function () {
                Route::get('materials', [MaterialController::class, 'index'])->name('materials.index');
                Route::post('materials', [MaterialController::class, 'store'])->name('materials.store');
                Route::put('materials/{material}', [MaterialController::class, 'update'])
                    ->whereNumber('material')
                    ->name('materials.update');

                Route::prefix('boms')->name('boms.')->group(function () {
                    Route::get('/', [BomController::class, 'index'])->name('index');
                    Route::post('/', [BomController::class, 'store'])->name('store');
                    Route::get('{bom}', [BomController::class, 'show'])->whereNumber('bom')->name('show');
                    Route::put('{bom}', [BomController::class, 'update'])->whereNumber('bom')->name('update');
                    Route::delete('{bom}', [BomController::class, 'destroy'])->whereNumber('bom')->name('destroy');
                    Route::post('{bom}/lines', [BomController::class, 'addLine'])->whereNumber('bom')->name('lines.store');
                    Route::put('{bom}/lines/{line}', [BomController::class, 'updateLine'])
                        ->whereNumber('bom')->whereNumber('line')->name('lines.update');
                    Route::delete('{bom}/lines/{line}', [BomController::class, 'destroyLine'])
                        ->whereNumber('bom')->whereNumber('line')->name('lines.destroy');
                });

                Route::prefix('routings')->name('routings.')->group(function () {
                    Route::get('/', [RoutingController::class, 'index'])->name('index');
                    Route::post('/', [RoutingController::class, 'store'])->name('store');
                    Route::get('{routing}', [RoutingController::class, 'show'])->whereNumber('routing')->name('show');
                    Route::put('{routing}', [RoutingController::class, 'update'])->whereNumber('routing')->name('update');
                    Route::delete('{routing}', [RoutingController::class, 'destroy'])->whereNumber('routing')->name('destroy');
                    Route::post('{routing}/operations', [RoutingController::class, 'addOperation'])
                        ->whereNumber('routing')->name('operations.store');
                    Route::put('{routing}/operations/{operation}', [RoutingController::class, 'updateOperation'])
                        ->whereNumber('routing')->whereNumber('operation')->name('operations.update');
                    Route::delete('{routing}/operations/{operation}', [RoutingController::class, 'destroyOperation'])
                        ->whereNumber('routing')->whereNumber('operation')->name('operations.destroy');
                });

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

            // 质量（M4：检验模板 / 检验单 / 不良记录）
            Route::prefix('quality')->name('quality.')->group(function () {
                Route::get('inspections', [InspectionController::class, 'index'])->name('inspections.index');
                Route::get('inspections/{inspection}', [InspectionController::class, 'show'])
                    ->whereNumber('inspection')->name('inspections.show');
                Route::post('inspections/{inspection}', [InspectionController::class, 'submit'])
                    ->whereNumber('inspection')->name('inspections.submit');

                Route::prefix('templates')->name('templates.')->group(function () {
                    Route::get('/', [InspectionTemplateController::class, 'index'])->name('index');
                    Route::post('/', [InspectionTemplateController::class, 'store'])->name('store');
                    Route::put('{template}', [InspectionTemplateController::class, 'update'])
                        ->whereNumber('template')->name('update');
                });

                Route::get('defects', [DefectController::class, 'index'])->name('defects.index');
                Route::post('defects', [DefectController::class, 'store'])->name('defects.store');
                Route::put('defects/{defect}', [DefectController::class, 'update'])
                    ->whereNumber('defect')->name('defects.update');
                Route::post('defects/{defect}/rework', [DefectController::class, 'rework'])
                    ->whereNumber('defect')->name('defects.rework');
            });

            // 设备（M5：台账 / 点检 / 报修）
            Route::get('equipment', [EquipmentController::class, 'index'])->name('equipment.index');
            Route::get('equipment/{equipment}', [EquipmentController::class, 'show'])
                ->whereNumber('equipment')->name('equipment.show');
            Route::put('equipment/{equipment}', [EquipmentController::class, 'update'])
                ->whereNumber('equipment')->name('equipment.update');
            Route::post('equipment/{equipment}/checks', [EquipmentController::class, 'check'])
                ->whereNumber('equipment')->name('equipment.checks.store');
            Route::post('equipment/{equipment}/repairs', [EquipmentController::class, 'repair'])
                ->whereNumber('equipment')->name('equipment.repairs.store');
            Route::put('equipment/repairs/{repair}', [EquipmentController::class, 'repairUpdate'])
                ->whereNumber('repair')->name('equipment.repairs.update');

            // 导入（M1-11~M1-14：模板下载 / 上传校验 / 预览 / 确认入库）
            Route::prefix('import')->name('import.')->group(function () {
                Route::get('/', [ImportController::class, 'index'])->name('index');
                Route::get('template/{type}', [ImportController::class, 'template'])->name('template');
                Route::post('{type}', [ImportController::class, 'upload'])->name('upload');
                Route::get('{type}/preview', [ImportController::class, 'preview'])->name('preview');
                Route::post('{type}/commit', [ImportController::class, 'commit'])->name('commit');
                Route::delete('{type}/preview', [ImportController::class, 'discard'])->name('discard');
            });

            // 系统（M6-04）：账号 / 角色权限 / 站内通知
            Route::prefix('system')->name('system.')->group(function () {
                Route::get('users', [SystemUserController::class, 'index'])->name('users.index');
                Route::put('users/{user}', [SystemUserController::class, 'update'])
                    ->whereNumber('user')->name('users.update');
                Route::get('roles', [SystemRoleController::class, 'index'])->name('roles.index');
                Route::put('roles/{role}', [SystemRoleController::class, 'update'])->name('roles.update');
                Route::get('notifications', [SystemNotificationController::class, 'index'])->name('notifications.index');
            });
        });
    });
