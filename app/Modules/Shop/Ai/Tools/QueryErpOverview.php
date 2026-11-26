<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ErpFormats;
use App\Modules\Shop\Models\Bill;
use App\Modules\Shop\Models\Customer;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Models\Supplier;
use App\Modules\Shop\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryErpOverview implements Tool
{
    use ErpFormats;

    public function description(): Stringable|string
    {
        return '获取 ERP 经营概览：商品数、低库存数、今日/本月销售额、待收货采购单、应收应付余额、客户与供应商数。所有数字均来自系统真实数据。';
    }

    public function handle(Request $request): Stringable|string
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $posToday = (float) PosOrder::where('status', PosOrder::STATUS_COMPLETED)
            ->where('order_date', '>=', $today)->sum('grand_total');
        $salesToday = (float) SalesOrder::whereIn('status', [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_RETURNED])
            ->whereDate('order_date', '>=', $today)->sum('grand_total');
        $todayRevenue = $posToday + $salesToday;

        $posMonth = (float) PosOrder::where('status', PosOrder::STATUS_COMPLETED)
            ->where('order_date', '>=', $monthStart)->sum('grand_total');
        $salesMonth = (float) SalesOrder::whereIn('status', [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_RETURNED])
            ->whereDate('order_date', '>=', $monthStart)->sum('grand_total');
        $monthRevenue = $posMonth + $salesMonth;

        $lowStockProducts = StockLevel::query()
            ->selectRaw('product_id, SUM(qty - reserved_qty) as available')
            ->groupBy('product_id')
            ->havingRaw('SUM(qty - reserved_qty) <= 10')
            ->count();

        $receivable = (float) Invoice::whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIAL])
            ->where('total_amount', '>', 0)
            ->selectRaw('SUM(total_amount - paid_amount) as balance')->value('balance');
        $payable = (float) Bill::whereIn('status', [Bill::STATUS_OPEN, Bill::STATUS_PARTIAL])
            ->where('total_amount', '>', 0)
            ->selectRaw('SUM(total_amount - paid_amount) as balance')->value('balance');

        $pendingReceipts = PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIAL])->count();

        return "ERP 经营概览（截至 " . now()->format('Y-m-d H:i') . "）：\n"
            . '- 商品：' . Product::where('is_active', true)->count() . ' 个在售，'
            . $lowStockProducts . " 个商品可用库存 ≤ 10\n"
            . '- 今日销售额：' . $this->money($todayRevenue)
            . '（POS ' . $this->money($posToday) . ' + 销售单 ' . $this->money($salesToday) . "）\n"
            . '- 本月销售额：' . $this->money($monthRevenue) . "\n"
            . '- 待收货采购单：' . $pendingReceipts . " 张\n"
            . '- 应收余额：' . $this->money($receivable) . '；应付余额：' . $this->money($payable) . "\n"
            . '- 客户：' . Customer::where('is_active', true)->count() . ' 个；供应商：'
            . Supplier::where('is_active', true)->count() . ' 个；员工账号：' . User::count() . ' 个';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
