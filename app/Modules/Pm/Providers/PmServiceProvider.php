<?php

declare(strict_types=1);

namespace App\Modules\Pm\Providers;

use App\Modules\Pm\Http\Middleware\AiApiAuth;
use App\Modules\Pm\Http\Middleware\CheckProjectAccess;
use App\Modules\Pm\Http\Middleware\CheckTaskAccess;
use App\Modules\Pm\Models\AiSetting;
use App\Modules\Pm\Models\Project;
use App\Modules\Pm\Models\Task;
use App\Modules\Pm\Policies\ProjectPolicy;
use App\Modules\Pm\Policies\TaskPolicy;
use App\Modules\Pm\Services\DashboardService;
use App\Modules\Pm\Services\FileService;
use App\Modules\Pm\Services\NotificationService;
use App\Modules\Pm\Services\ProjectService;
use App\Modules\Pm\Services\TaskService;
use App\Modules\Pm\Services\TrashService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * 研发项目管理 的模块装配器。
 *
 * 装路由、装视图、装中间件别名、装 Policy 与限流。
 * 不 loadMigrationsFrom：五个模块同时注册迁移路径会让
 * `migrate --database=pm` 把其他产品的表也建进 pm 库（HC-17），
 * 迁移一律用显式 --path 指向本模块目录。
 */
class PmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 原项目在 AppServiceProvider 里注册的服务层单例，原样保留
        $this->app->singleton(ProjectService::class);
        $this->app->singleton(TaskService::class);
        $this->app->singleton(FileService::class);
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(NotificationService::class);

        // PM 的产品级配置（AI 默认值、分页等）
        $configPath = base_path('config/pm.php');
        if (is_file($configPath)) {
            $this->mergeConfigFrom($configPath, 'pm');
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'pm');

        // 原项目的三个中间件别名（bootstrap/app.php 的全局别名区不再挂产品中间件）
        Route::aliasMiddleware('project.access', CheckProjectAccess::class);
        Route::aliasMiddleware('task.access', CheckTaskAccess::class);
        Route::aliasMiddleware('ai.api.auth', AiApiAuth::class);

        // Bootstrap 5 分页（原项目 AppServiceProvider 里的设置）
        Paginator::useBootstrapFive();

        // Policy
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);

        $this->registerAiRateLimiter();
        $this->registerTrashDeletingHook();
    }

    /**
     * AI 每用户限流（每分钟/每日，管理员可在「AI 设置」调整）。
     * 表无数据时退化为硬编码默认值，避免演示环境因空表 500。
     */
    protected function registerAiRateLimiter(): void
    {
        RateLimiter::for('ai', function ($request) {
            try {
                $setting = AiSetting::current();
                $minute = max(1, (int) ($setting?->rate_limit_minute ?: 30));
                $daily = max(1, (int) ($setting?->rate_limit_daily ?: 200));
            } catch (\Throwable $e) {
                $minute = 30;
                $daily = 200;
            }

            $key = $request->user()?->id ?: 'guest:'.$request->ip();

            return [
                Limit::perMinute($minute)->by($key),
                Limit::perDay($daily)->by($key),
            ];
        });
    }

    /**
     * 软删除时把操作人写入 deleted_by（个人回收站依赖此字段）。
     * 与原项目完全一致：统一挂 deleting 事件，不逐个改删除入口。
     */
    protected function registerTrashDeletingHook(): void
    {
        if (! class_exists(TrashService::class)) {
            return;
        }

        foreach (TrashService::TYPES as $config) {
            // 裁剪掉的模型（协作文档/企微文件）类型里可能残留，跳过即可
            if (! class_exists($config['model'])) {
                continue;
            }

            $config['model']::deleting(function ($model) {
                if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                    return;
                }

                if (! TrashService::hasDeletedByColumn($model)) {
                    return;
                }

                try {
                    $userId = auth('pm')->id();

                    DB::connection('pm')->table($model->getTable())
                        ->where($model->getKeyName(), $model->getKey())
                        ->update(['deleted_by' => $userId]);

                    $model->deleted_by = $userId;
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }
    }
}
