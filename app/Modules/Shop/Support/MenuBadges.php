<?php

namespace App\Modules\Shop\Support;

use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 侧边栏菜单徽章计数（同一请求内只查一次）。
 *
 * 数量为 0 时前端不渲染徽章；查询异常时退化为 0，保证菜单本身永远可用。
 */
final class MenuBadges
{
    /** @var array{pending_receive:int,pending_ship:int,low_stock:int}|null */
    private static ?array $cache = null;

    /**
     * @return array{pending_receive:int,pending_ship:int,low_stock:int}
     */
    public static function counts(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            return self::$cache = [
                'pending_receive' => PurchaseOrder::query()
                    ->whereIn('status', [PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIAL])
                    ->count(),
                'pending_ship' => SalesOrder::query()
                    ->where('status', SalesOrder::STATUS_CONFIRMED)
                    ->count(),
                'low_stock' => StockLevel::query()
                    ->select('product_id', DB::raw('SUM(qty - reserved_qty) AS available'))
                    ->groupBy('product_id')
                    ->havingRaw('SUM(qty - reserved_qty) <= ?', [5])
                    ->get()
                    ->count(),
            ];
        } catch (Throwable) {
            return self::$cache = [
                'pending_receive' => 0,
                'pending_ship' => 0,
                'low_stock' => 0,
            ];
        }
    }
}
