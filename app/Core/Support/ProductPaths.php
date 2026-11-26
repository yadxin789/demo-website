<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * 产品相关路径的唯一出口。
 *
 * 硬约束：任何代码不得自己拼 storage_path('logs/xxx') —— 必须走这里，
 * 否则改目录结构时要全项目搜索。
 */
final class ProductPaths
{
    /** 会话：单目录。五 guard 的 session key 天然不同，物理分目录反而要五套 cookie */
    public static function sessions(): string
    {
        return storage_path('framework/sessions');
    }

    public static function cache(): string
    {
        return storage_path('framework/cache');
    }

    public static function views(): string
    {
        return storage_path('framework/views');
    }

    public static function logs(string $code): string
    {
        return storage_path("logs/{$code}");
    }

    public static function uploads(string $code): string
    {
        return storage_path("app/public/{$code}");
    }

    public static function assets(string $code): string
    {
        return public_path("assets/{$code}");
    }
}