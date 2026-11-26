<?php

namespace App\Modules\Shop\Support;

use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\Product;

/**
 * 扫码内容规范：前缀用于区分商品、库位、序列号、批次与付款码。
 * 二维码优先使用前缀；一维条码保留原始值（兼容既有 EAN/UPC/CODE128 标签）。
 */
class ScanCode
{
    public const PRODUCT = 'product';
    public const LOCATION = 'location';
    public const SERIAL = 'serial';
    public const LOT = 'lot';
    public const ORDER = 'order';
    public const PAYMENT = 'payment';

    private const PREFIXES = [
        'ERP-PRODUCT:' => self::PRODUCT,
        'ERP-LOCATION:' => self::LOCATION,
        'ERP-SERIAL:' => self::SERIAL,
        'ERP-LOT:' => self::LOT,
        'ERP-ORDER:' => self::ORDER,
        'ERP-PAY:' => self::PAYMENT,
    ];

    public static function product(Product $product): string
    {
        return 'ERP-PRODUCT:' . $product->code;
    }

    public static function location(Location $location): string
    {
        return 'ERP-LOCATION:' . $location->id;
    }

    public static function serial(string $serialNo): string
    {
        return 'ERP-SERIAL:' . $serialNo;
    }

    public static function lot(string $lotNo): string
    {
        return 'ERP-LOT:' . $lotNo;
    }

    public static function order(string $orderCode): string
    {
        return 'ERP-ORDER:' . $orderCode;
    }

    public static function payment(string $token): string
    {
        return 'ERP-PAY:' . $token;
    }

    /**
     * 解析带前缀/JSON 的扫码值；普通条码返回 null，由 ScanResolver 用数据库精确匹配。
     *
     * @return array{type: string, value: string}|null
     */
    public static function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '{')) {
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['type'], $json['code'])) {
                $type = (string) $json['type'];
                if (in_array($type, self::types(), true)) {
                    return ['type' => $type, 'value' => (string) $json['code']];
                }
            }
        }

        foreach (self::PREFIXES as $prefix => $type) {
            if (str_starts_with($raw, $prefix)) {
                $value = substr($raw, strlen($prefix));
                if ($value !== '') {
                    return ['type' => $type, 'value' => $value];
                }
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public static function types(): array
    {
        return [
            self::PRODUCT,
            self::LOCATION,
            self::SERIAL,
            self::LOT,
            self::ORDER,
            self::PAYMENT,
        ];
    }
}
