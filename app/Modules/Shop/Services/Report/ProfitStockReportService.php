<?php

namespace App\Modules\Shop\Services\Report;

use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\SalesOrderLine;
use App\Modules\Shop\Models\StockLevel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 毛利与存货估值报表。
 * 存货估值 = 结存数量 × 标准成本；毛利 = 销售净额 − 销售数量 × 标准成本。
 */
class ProfitStockReportService
{
    /**
     * 存货估值：按商品汇总在库数量与标准成本估值。
     *
     * @return array{rows: Collection, totalQty: float, totalValuation: float}
     */
    public function inventoryValuation(?int $productId): array
    {
        $levels = StockLevel::query()
            ->with('product')
            ->when($productId !== null, fn ($q) => $q->where('product_id', $productId))
            ->get();

        $rows = $levels
            ->groupBy('product_id')
            ->map(function (Collection $group) {
                $product = $group->first()->product;
                $qty = round($group->sum('qty'), 4);
                $standardCost = (float) ($product?->standard_cost ?? 0);
                $valuation = round($qty * $standardCost, 4);

                return [
                    'product_id' => $product?->id,
                    'code' => $product?->code ?? '—',
                    'name' => $product?->name ?? '未知商品',
                    'qty' => $qty,
                    'standard_cost' => $standardCost,
                    'valuation' => $valuation,
                ];
            })
            ->sortBy('name')
            ->values();

        $totalQty = round($rows->sum('qty'), 4);
        $totalValuation = round($rows->sum('valuation'), 4);

        return compact('rows', 'totalQty', 'totalValuation');
    }

    /**
     * 毛利分析：按商品汇总已发货销售的净额、标准成本与毛利。
     *
     * @return array{rows: Collection, revenue: float, cost: float, grossProfit: float, margin: float}
     */
    public function profitMargin(?Carbon $start, ?Carbon $end, ?int $productId): array
    {
        $lines = SalesOrderLine::query()
            ->with(['order', 'product'])
            ->whereHas('order', function ($q) use ($start, $end) {
                $q->where('status', SalesOrder::STATUS_SHIPPED)
                    ->when($start !== null, fn ($e) => $e->whereDate('order_date', '>=', $start->toDateString()))
                    ->when($end !== null, fn ($e) => $e->whereDate('order_date', '<=', $end->toDateString()));
            })
            ->when($productId !== null, fn ($q) => $q->where('product_id', $productId))
            ->get();

        $rows = $lines
            ->groupBy('product_id')
            ->map(function (Collection $group) {
                $product = $group->first()->product;
                $qty = round($group->sum('qty'), 4);
                $revenue = round($group->sum('subtotal'), 4);
                $standardCost = (float) ($product?->standard_cost ?? 0);
                $cost = round($qty * $standardCost, 4);
                $grossProfit = round($revenue - $cost, 4);
                $margin = $revenue > 0 ? round($grossProfit / $revenue * 100, 2) : 0.0;

                return [
                    'product_id' => $product?->id,
                    'code' => $product?->code ?? '—',
                    'name' => $product?->name ?? '未知商品',
                    'qty' => $qty,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'grossProfit' => $grossProfit,
                    'margin' => $margin,
                ];
            })
            ->sortByDesc('grossProfit')
            ->values();

        $revenue = round($rows->sum('revenue'), 4);
        $cost = round($rows->sum('cost'), 4);
        $grossProfit = round($revenue - $cost, 4);
        $margin = $revenue > 0 ? round($grossProfit / $revenue * 100, 2) : 0.0;

        return compact('rows', 'revenue', 'cost', 'grossProfit', 'margin');
    }

    /** 商品下拉（在售）。 */
    public function products(): Collection
    {
        return Product::query()->orderBy('code')->get(['id', 'code', 'name']);
    }
}
