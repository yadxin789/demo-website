<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\PaymentMethod;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\PosPayment;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Models\Warehouse;
use App\Modules\Shop\Services\Finance\AgingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 仪表盘数据聚合：KPI、趋势、结构、排行与待办，全部取自本地库。
 *
 * 视图只负责渲染，图表通过 @json 注入后在浏览器端初始化。
 */
class DashboardService
{
    public const TREND_DAYS = 14;
    public const RANK_DAYS = 30;

    public function __construct(private readonly AgingService $aging)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        $posToday = PosOrder::query()
            ->whereDate('order_date', $today)
            ->where('status', PosOrder::STATUS_COMPLETED);

        $posSales = (float) (clone $posToday)->sum('grand_total');
        $posCount = (clone $posToday)->count();

        $salesToday = SalesOrder::query()
            ->whereDate('order_date', $today)
            ->whereIn('status', [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_RETURNED]);

        $salesAmount = (float) (clone $salesToday)->sum('grand_total');
        $salesCount = (clone $salesToday)->count();

        $pendingShip = SalesOrder::where('status', SalesOrder::STATUS_CONFIRMED)->count();
        $pendingReceive = PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIAL])->count();

        $receivable = (float) Invoice::query()
            ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIAL])
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) AS balance')
            ->value('balance');

        $stock = StockLevel::query()
            ->selectRaw('COALESCE(SUM(qty * avg_cost), 0) AS value, COALESCE(SUM(qty), 0) AS qty')
            ->first();

        $stockValue = (float) ($stock->value ?? 0);
        $stockQty = (float) ($stock->qty ?? 0);

        return [
            'today' => $today->toDateString(),
            'posSales' => round($posSales, 2),
            'posCount' => $posCount,
            'salesAmount' => round($salesAmount, 2),
            'salesCount' => $salesCount,
            'pendingShip' => $pendingShip,
            'pendingReceive' => $pendingReceive,
            'receivable' => round($receivable, 2),
            'stockValue' => round($stockValue, 2),
            'stockQty' => round($stockQty, 2),
            'avgTicket' => $posCount > 0 ? round($posSales / $posCount, 2) : 0.0,
            'lowStock' => $this->lowStockProducts(),
            'salesTrend' => $this->salesTrend($today),
            'paymentMix' => $this->paymentMix($today),
            'stockByWarehouse' => $this->stockByWarehouse(),
            'topProducts' => $this->topProducts($today),
            'arAging' => $this->arAging($today),
            'recentOrders' => PosOrder::with('cashier')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'recentAudits' => AuditLog::query()
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'module', 'action', 'message', 'acted_at', 'document_no']),
        ];
    }

    /**
     * 近 N 天 POS 与销售订单金额趋势，缺失日期补 0。
     *
     * @return array{labels:list<string>, pos:list<float>, sales:list<float>}
     */
    private function salesTrend(Carbon $today): array
    {
        $start = $today->copy()->subDays(self::TREND_DAYS - 1)->startOfDay();

        $posRows = PosOrder::query()
            ->where('status', PosOrder::STATUS_COMPLETED)
            ->whereBetween('order_date', [$start, $today->copy()->endOfDay()])
            ->selectRaw('DATE(order_date) AS day, SUM(grand_total) AS amount')
            ->groupByRaw('DATE(order_date)')
            ->get();

        $salesRows = SalesOrder::query()
            ->whereIn('status', [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_RETURNED])
            ->whereBetween('order_date', [$start, $today->copy()->endOfDay()])
            ->selectRaw('DATE(order_date) AS day, SUM(grand_total) AS amount')
            ->groupByRaw('DATE(order_date)')
            ->get();

        $posByDay = $posRows->mapWithKeys(fn ($row) => [(string) $row->day => (float) $row->amount]);
        $salesByDay = $salesRows->mapWithKeys(fn ($row) => [(string) $row->day => (float) $row->amount]);

        $labels = [];
        $pos = [];
        $sales = [];
        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('m-d');
            $pos[] = round($posByDay->get($key, 0.0), 2);
            $sales[] = round($salesByDay->get($key, 0.0), 2);
        }

        return ['labels' => $labels, 'pos' => $pos, 'sales' => $sales];
    }

    /**
     * 近 N 天 POS 收款按支付方式构成。
     *
     * @return array{labels:list<string>, values:list<float>, total:float}
     */
    private function paymentMix(Carbon $today): array
    {
        $start = $today->copy()->subDays(self::RANK_DAYS - 1)->startOfDay();

        $rows = PosPayment::query()
            ->whereHas('order', function ($query) use ($start) {
                $query->where('status', PosOrder::STATUS_COMPLETED)
                    ->whereDate('order_date', '>=', $start);
            })
            ->selectRaw('method_id, SUM(amount) AS total')
            ->groupBy('method_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $methods = PaymentMethod::query()
            ->whereIn('id', $rows->pluck('method_id'))
            ->pluck('name', 'id');

        $labels = $rows->map(fn ($row) => (string) ($methods[$row->method_id] ?? '未指定方式'))->values()->all();
        $values = $rows->map(fn ($row) => round((float) $row->total, 2))->values()->all();

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => round(array_sum($values), 2),
        ];
    }

    /**
     * 按仓库汇总库存数量与库存金额。
     *
     * @return array{labels:list<string>, values:list<float>, quantities:list<float>, total:float}
     */
    private function stockByWarehouse(): array
    {
        $rows = StockLevel::query()
            ->selectRaw('warehouse_id, SUM(qty) AS qty, SUM(qty * avg_cost) AS value')
            ->groupBy('warehouse_id')
            ->orderByDesc('value')
            ->limit(8)
            ->get();

        $warehouses = Warehouse::query()
            ->whereIn('id', $rows->pluck('warehouse_id'))
            ->pluck('name', 'id');

        $labels = $rows->map(fn ($row) => (string) ($warehouses[$row->warehouse_id] ?? '仓库 #'.$row->warehouse_id))->values()->all();
        $values = $rows->map(fn ($row) => round((float) $row->value, 2))->values()->all();
        $quantities = $rows->map(fn ($row) => round((float) $row->qty, 2))->values()->all();

        return [
            'labels' => $labels,
            'values' => $values,
            'quantities' => $quantities,
            'total' => round(array_sum($values), 2),
        ];
    }

    /**
     * 近 N 天 POS 销量 Top 8。
     *
     * @return array{labels:list<string>, values:list<float>}
     */
    private function topProducts(Carbon $today): array
    {
        $start = $today->copy()->subDays(self::RANK_DAYS - 1)->startOfDay();

        $rows = DB::table('pos_order_lines as lines')
            ->join('pos_orders as orders', 'orders.id', '=', 'lines.order_id')
            ->where('orders.status', PosOrder::STATUS_COMPLETED)
            ->whereDate('orders.order_date', '>=', $start)
            ->selectRaw('lines.product_id, SUM(lines.qty) AS qty')
            ->groupBy('lines.product_id')
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        $products = Product::query()
            ->whereIn('id', $rows->pluck('product_id'))
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $labels = $rows->map(function ($row) use ($products): string {
            $product = $products->get($row->product_id);

            return $product ? Str::limit($product->name, 16) : '商品 #'.$row->product_id;
        })->values()->all();

        return [
            'labels' => $labels,
            'values' => $rows->map(fn ($row) => round((float) $row->qty, 2))->values()->all(),
        ];
    }

    /**
     * 应收账龄分段金额。
     *
     * @return array{labels:list<string>, values:list<float>, total:float}
     */
    private function arAging(Carbon $today): array
    {
        $result = $this->aging->receivables(null, $today);
        $totals = $result['totals'];

        $labels = [];
        $values = [];
        foreach (AgingService::BUCKETS as $bucket) {
            $labels[] = AgingService::BUCKET_LABELS[$bucket];
            $values[] = round((float) ($totals[$bucket] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => round(array_sum($values), 2),
        ];
    }

    /**
     * 低库存商品：按商品汇总可用量（现存量 − 预留量），低于阈值 5 视为需要补货。
     *
     * @return Collection<int, array{code: string, name: string, available: float}>
     */
    private function lowStockProducts(): Collection
    {
        $rows = StockLevel::query()
            ->select('product_id', DB::raw('SUM(qty - reserved_qty) AS available'))
            ->groupBy('product_id')
            ->havingRaw('SUM(qty - reserved_qty) <= ?', [5])
            ->orderBy('available')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $products = Product::whereIn('id', $rows->pluck('product_id'))
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        return $rows->map(fn ($row) => [
            'code' => (string) ($products[$row->product_id]->code ?? '—'),
            'name' => (string) ($products[$row->product_id]->name ?? '已删除商品'),
            'available' => round((float) $row->available, 2),
        ])->filter(fn ($row) => $row['name'] !== '已删除商品')->values();
    }
}
