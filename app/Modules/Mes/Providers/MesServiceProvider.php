<?php

declare(strict_types=1);

namespace App\Modules\Mes\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * 智能制造 MES 预留位（enabled = false）。
 *
 * HC-29：本产品在正式开发前只允许存在"占位"，不装路由、不装视图、不装迁移。
 * 打开方式：把 config/products.php 里的 enabled 改 true，并替换本文件为完整版本。
 */
class MesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('products.products.mes.enabled', false)) {
            return;
        }

        // 产品尚未开发（S5 / S6 阶段填充）
    }
}