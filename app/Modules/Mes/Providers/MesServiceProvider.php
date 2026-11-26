<?php

declare(strict_types=1);

namespace App\Modules\Mes\Providers;

use App\Modules\Mes\Services\ApsService;
use App\Modules\Mes\Services\DispatchService;
use App\Modules\Mes\Services\InspectionService;
use App\Modules\Mes\Services\IssueService;
use App\Modules\Mes\Services\KanbanService;
use App\Modules\Mes\Services\ReportService;
use App\Modules\Mes\Services\WorkOrderService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

/**
 * 智能制造 MES 的模块装配器（M0-01 起启用）。
 *
 * 装路由、装视图、注册服务层单例。
 * 不 loadMigrationsFrom：五个模块同时注册迁移路径会让 migrate 把别的产品的表
 * 建进本库（HC-17），迁移一律用显式 --path 指向本模块目录。
 */
class MesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            WorkOrderService::class,
            DispatchService::class,
            ReportService::class,
            IssueService::class,
            InspectionService::class,
            ApsService::class,
            KanbanService::class,
        ] as $service) {
            if (class_exists($service)) {
                $this->app->singleton($service);
            }
        }

        $configPath = base_path('config/mes.php');
        if (is_file($configPath)) {
            $this->mergeConfigFrom($configPath, 'mes');
        }
    }

    public function boot(): void
    {
        // HC-30：enabled=false 时本产品不装任何路由与视图，URL 一律 404
        if (! config('products.products.mes.enabled', false)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'mes');

        Paginator::useBootstrapFive();
    }
}
