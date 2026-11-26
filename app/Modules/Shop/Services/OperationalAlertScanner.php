<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\Lot;
use App\Modules\Shop\Models\Notification;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\StockLevel;
use Illuminate\Support\Carbon;

/**
 * 经营巡检：把需要人跟进的异常汇总成站内消息，按角色投递。
 */
class OperationalAlertScanner
{
    public const LOW_STOCK_THRESHOLD = 5.0;
    public const EXPIRY_WARNING_DAYS = 30;

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * @return array{low_stock:int, expiring_lots:int, pending_receipts:int, overdue_receivables:int}
     */
    public function scan(?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        return [
            'low_stock' => $this->scanLowStock($today),
            'expiring_lots' => $this->scanExpiringLots($today),
            'pending_receipts' => $this->scanPendingReceipts($today),
            'overdue_receivables' => $this->scanOverdueReceivables($today),
        ];
    }

    private function scanLowStock(Carbon $today): int
    {
        $rows = StockLevel::query()
            ->selectRaw('product_id, SUM(qty - reserved_qty) AS available')
            ->groupBy('product_id')
            ->havingRaw('SUM(qty - reserved_qty) <= ?', [self::LOW_STOCK_THRESHOLD])
            ->orderBy('available')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $products = Product::query()
            ->whereIn('id', $rows->pluck('product_id'))
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $lines = $rows->take(5)->map(function ($row) use ($products): string {
            $product = $products->get($row->product_id);
            $label = $product ? $product->code.' '.$product->name : '商品 #'.$row->product_id;

            return '· '.$label.'：可用 '.rtrim(rtrim(number_format((float) $row->available, 2, '.', ''), '0'), '.').'（阈值 '.self::LOW_STOCK_THRESHOLD.'）';
        })->implode("\n");

        return $this->notifications->sendToRoles(
            ['warehouse', 'manager', 'super-admin'],
            Notification::TYPE_STOCK,
            Notification::LEVEL_WARNING,
            '低库存预警：'.$rows->count().' 个商品',
            "共 {$rows->count()} 个商品可用库存低于阈值，请及时补货：\n".$lines,
            'products.index',
            'ops:low-stock:'.$today->toDateString(),
        );
    }

    private function scanExpiringLots(Carbon $today): int
    {
        $lots = Lot::query()
            ->where('status', Lot::STATUS_ACTIVE)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(self::EXPIRY_WARNING_DAYS))
            ->with('product:id,code,name')
            ->orderBy('expiry_date')
            ->get();

        if ($lots->isEmpty()) {
            return 0;
        }

        $lines = $lots->take(5)->map(function (Lot $lot) use ($today): string {
            $days = (int) $today->diffInDays($lot->expiry_date, false);
            $state = $days < 0
                ? '已过期 '.abs($days).' 天'
                : ($days === 0 ? '今日到期' : '剩 '.$days.' 天');
            $product = $lot->product ? $lot->product->code.' '.$lot->product->name : '商品 #'.$lot->product_id;

            return '· '.$product.' / 批次 '.$lot->lot_no.'：'.$lot->expiry_date->toDateString().'（'.$state.'）';
        })->implode("\n");

        return $this->notifications->sendToRoles(
            ['warehouse', 'manager', 'super-admin'],
            Notification::TYPE_LOT,
            Notification::LEVEL_WARNING,
            '批次临期提醒：'.$lots->count().' 个批次',
            '以下批次已过期或将在 '.self::EXPIRY_WARNING_DAYS." 天内到期：\n".$lines,
            'lots.index',
            'ops:expiring-lots:'.$today->toDateString(),
        );
    }

    private function scanPendingReceipts(Carbon $today): int
    {
        $orders = PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIAL])
            ->orderBy('expected_date')
            ->orderBy('code')
            ->get(['id', 'code', 'status', 'expected_date']);

        if ($orders->isEmpty()) {
            return 0;
        }

        $lines = $orders->take(5)->map(function (PurchaseOrder $order): string {
            $expected = $order->expected_date ? $order->expected_date->toDateString() : '未填预计到货日';

            return '· '.$order->code.'：'.$order->status.' / 预计 '.$expected;
        })->implode("\n");

        return $this->notifications->sendToRoles(
            ['purchase', 'manager', 'super-admin'],
            Notification::TYPE_PURCHASE,
            Notification::LEVEL_INFO,
            '待收货采购单：'.$orders->count().' 张',
            "以下采购单仍未完成收货，请跟进供应商：\n".$lines,
            'purchase-orders.index',
            'ops:pending-receipts:'.$today->toDateString(),
        );
    }

    private function scanOverdueReceivables(Carbon $today): int
    {
        $invoices = Invoice::query()
            ->whereNotIn('status', [Invoice::STATUS_VOID, Invoice::STATUS_PAID])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereRaw('(total_amount - paid_amount) > 0')
            ->with('partner:id,code,name')
            ->orderBy('due_date')
            ->get();

        if ($invoices->isEmpty()) {
            return 0;
        }

        $total = $invoices->sum(fn (Invoice $invoice): float => $invoice->balance());
        $lines = $invoices->take(5)->map(function (Invoice $invoice): string {
            $partner = $invoice->partner ? $invoice->partner->name : '客户 #'.$invoice->partner_id;

            return '· '.$invoice->code.' / '.$partner.'：逾期 '.$invoice->due_date->toDateString().'，未收 ¥'.number_format($invoice->balance(), 2);
        })->implode("\n");

        return $this->notifications->sendToRoles(
            ['finance', 'manager', 'super-admin'],
            Notification::TYPE_FINANCE,
            Notification::LEVEL_DANGER,
            '应收逾期提醒：'.$invoices->count().' 张',
            '共 '.$invoices->count().' 张应收发票逾期，未收合计 ¥'.number_format($total, 2)."，请尽快催收：\n".$lines,
            'aging.index',
            'ops:overdue-receivables:'.$today->toDateString(),
        );
    }
}
