<?php

declare(strict_types=1);

namespace App\Core\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Core 装配器。
 *
 * 注意：门户路由已由 bootstrap/app.php 的 withRouting(web: routes/web.php) 自动装载，
 * 这里不重复 loadRoutesFrom（重复装载会导致路由注册两次）。
 * 这里只负责：门户视图命名空间 + 未来可能的核心绑定。
 */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/portal'), 'portal');
    }
}