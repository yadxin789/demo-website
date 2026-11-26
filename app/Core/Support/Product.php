<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * 产品读取器：唯一允许"读 config/products.php"的入口。
 *
 * 硬约束（HC-04）：Core 不做业务，只做"知道有哪些产品、当前是哪个"。
 * 任何业务判断（这个产品能干什么、有哪些菜单）都必须回到 Modules/{X} 内。
 */
final class Product
{
    private static ?string $current = null;

    /** 全部产品（含 disabled） */
    public static function all(): array
    {
        return config('products.products', []);
    }

    /** 只取已启用产品，按 order 升序 */
    public static function enabled(): array
    {
        $items = array_filter(self::all(), static fn (array $p): bool => ($p['enabled'] ?? false) === true);
        uasort($items, static fn (array $a, array $b): int => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));

        return $items;
    }

    public static function find(?string $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::all()[$code] ?? null;
    }

    public static function isEnabled(string $code): bool
    {
        return (bool) (self::find($code)['enabled'] ?? false);
    }

    public static function current(): ?string
    {
        return self::$current;
    }

    public static function currentConfig(): ?array
    {
        return self::find(self::$current);
    }

    public static function setCurrent(string $code): void
    {
        self::$current = $code;
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    /** 取某产品的某个配置项 */
    public static function config(string $code, string $key, mixed $default = null): mixed
    {
        return self::find($code)[$key] ?? $default;
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }
}