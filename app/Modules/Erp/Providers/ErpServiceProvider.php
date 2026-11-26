<?php

declare(strict_types=1);

namespace App\Modules\Erp\Providers;

use App\Modules\Erp\Http\Middleware\SensitiveAccessLogMiddleware;
use App\Modules\Erp\Models\Rnd\RndProject;
use App\Modules\Erp\Models\Rnd\RndProjectDocument;
use App\Modules\Erp\Models\Rnd\RndProjectTask;
use App\Modules\Erp\Models\System\MediaFile;
use App\Modules\Erp\Policies\MediaFilePolicy;
use App\Modules\Erp\Policies\RndProjectDocumentPolicy;
use App\Modules\Erp\Policies\RndProjectPolicy;
use App\Modules\Erp\Policies\RndProjectTaskPolicy;
use App\Modules\Erp\Services\OperationLogger;
use App\Modules\Erp\Support\TenancyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * 电子制造 ERP 的模块装配器。
 *
 * 只装本产品自己的东西：路由、视图、中间件别名、Policy、请求级单例。
 * 不注册全局单例、不改别的产品的配置（HC-01）。
 * 迁移刻意不 loadMigrationsFrom：五模块都注册路径会让 migrate --database=pm 把 ERP 的表建进 pm 库（HC-17）。
 */
class ErpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 租户上下文按请求作用域隔离，避免跨请求残留（源项目 P3-4）
        $this->app->scoped(TenancyContext::class, fn () => new TenancyContext());
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'erp');

        // 中间件别名：只给 ERP 路由用。spatie 的三个别名指向的是第三方类，
        // 其它产品若要用，请注册自己的别名并显式传 guard，不要复用这里的名字（HC-01）。
        $router = $this->app['router'];
        $router->aliasMiddleware('sensitive', SensitiveAccessLogMiddleware::class);
        $router->aliasMiddleware('role', \Spatie\Permission\Middleware\RoleMiddleware::class);
        $router->aliasMiddleware('permission', \Spatie\Permission\Middleware\PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class);

        // 对象级授权（源项目 P0-05 / P0-07）
        Gate::policy(RndProject::class, RndProjectPolicy::class);
        Gate::policy(RndProjectTask::class, RndProjectTaskPolicy::class);
        Gate::policy(RndProjectDocument::class, RndProjectDocumentPolicy::class);
        Gate::policy(MediaFile::class, MediaFilePolicy::class);

        // 操作日志：源项目是对全部 Eloquent 事件监听。
        // 单入口多产品下必须限定在 App\Modules\Erp\ 命名空间内，
        // 否则 PM / Shop 的写操作会被记进 erp 库的 operation_logs（HC-01 / HC-15）。
        Event::listen('eloquent.*', function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;

            if (! $model instanceof Model) {
                return;
            }

            if (! str_starts_with($model::class, 'App\\Modules\\Erp\\')) {
                return;
            }

            app(OperationLogger::class)->handle($eventName, $model);
        });
    }
}
