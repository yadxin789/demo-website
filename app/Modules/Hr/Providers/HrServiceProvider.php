<?php

declare(strict_types=1);

namespace App\Modules\Hr\Providers;

use App\Modules\Hr\Services\AttendanceService;
use App\Modules\Hr\Services\DashboardService;
use App\Modules\Hr\Services\EmployeeService;
use App\Modules\Hr\Services\LeaveService;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Hr\Services\PerformanceService;
use App\Modules\Hr\Services\RecruitmentService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

/**
 * 人事管理 HR 的模块装配器（H0-01 起启用）。
 *
 * 装路由、装视图、注册服务层单例。
 * 不 loadMigrationsFrom：五个模块同时注册迁移路径会让 migrate 把别的产品的表
 * 建进本库（HC-17），迁移一律用显式 --path 指向本模块目录。
 */
class HrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            EmployeeService::class,
            AttendanceService::class,
            LeaveService::class,
            PayrollService::class,
            RecruitmentService::class,
            PerformanceService::class,
            DashboardService::class,
        ] as $service) {
            if (class_exists($service)) {
                $this->app->singleton($service);
            }
        }

        $configPath = base_path('config/hr.php');
        if (is_file($configPath)) {
            $this->mergeConfigFrom($configPath, 'hr');
        }
    }

    public function boot(): void
    {
        // HC-30：enabled=false 时本产品不装任何路由与视图，URL 一律 404
        if (! config('products.products.hr.enabled', false)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'hr');

        Paginator::useBootstrapFive();
    }
}
