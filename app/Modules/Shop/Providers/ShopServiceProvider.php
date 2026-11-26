<?php

declare(strict_types=1);

namespace App\Modules\Shop\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * 百货零售 ERP 的模块装配器。
 *
 * 只做三件事：装路由、装视图命名空间、装迁移。
 * 不允许注册全局单例、不允许改其他产品的配置（HC-01）。
 */
class ShopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'shop');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}