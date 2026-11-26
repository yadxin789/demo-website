<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Models\AccountEntry;
use App\Modules\Shop\Models\AiChangeProposal;
use App\Modules\Shop\Models\BankStatement;
use App\Modules\Shop\Models\Bill;
use App\Modules\Shop\Models\FiscalPeriod;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\Lot;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\PosOrderLine;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\PurchaseOrderLine;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\SalesOrderLine;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Models\StockCount;
use App\Modules\Shop\Models\StockMovement;
use App\Modules\Shop\Models\User;
use App\Modules\Shop\Models\Warehouse;
use App\Modules\Shop\Services\Accounting\BillService;
use App\Modules\Shop\Services\Inventory\CountService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * AI 定时自动化：所有分析都是「只读」。
 *
 * 生成采购草稿/改价时也只创建 AiChangeProposal，
 * 必须由本人在 AI 授权中心确认后才会真正写业务数据。
 */
class AiAutomationService
{
    public function __construct(
        private readonly AiService $ai,
        private readonly AiChangeProposalService $proposals,
        private readonly CountService $counts,
        private readonly BillService $bills,
    ) {
    }

    /**
     * 每日补货建议。
     *
     * 销量口径：近 30 天销售出库（sales_order / pos_order）扣减销售退货入库。
     */
    public function replenishment(?Carbon $asOf = null, int $limit = 20): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $threshold = (float) config('ai.automation.low_stock_threshold', 10);
        $coverDays = max(1, (int) config('ai.automation.replenishment_cover_days', 14));

        $stock = StockLevel::query()
            ->selectRaw('product_id, SUM(qty) as qty, SUM(reserved_qty) as reserved_qty, SUM(qty - reserved_qty) as available')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $start = $asOf->copy()->subDays(30);
        $end = $asOf->copy()->endOfDay();

        $soldOut = StockMovement::query()
            ->where('direction', StockMovement::DIRECTION_OUT)
            ->whereIn('source_type', ['sales_order', 'pos_order'])
            ->whereBetween('movement_date', [$start, $end])
            ->selectRaw('product_id, SUM(qty) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $returnedIn = StockMovement::query()
            ->where('direction', StockMovement::DIRECTION_IN)
            ->where('source_type', 'sales_return')
            ->whereBetween('movement_date', [$start, $end])
            ->selectRaw('product_id, SUM(qty) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $warehouse = Warehouse::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'standard_cost', 'avg_cost']);

        $items = [];

        foreach ($products as $product) {
            $available = round((float) ($stock[$product->id]->available ?? 0), 4);
            if ($available > $threshold) {
                continue;
            }

            $sold = max(0.0, (float) ($soldOut[$product->id] ?? 0) - (float) ($returnedIn[$product->id] ?? 0));
            $daily = $sold > 0 ? $sold / 30 : 0.0;
            $target = max($threshold, $daily * $coverDays);
            $suggested = round(max(0.0, $target - $available), 2);
            if ($suggested <= 0) {
                continue;
            }

            $supplier = $this->latestSupplier($product->id);
            $unitPrice = $supplier['unit_price'] ?? (float) ($product->avg_cost ?: $product->standard_cost);

            $items[] = [
                'product_id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'available' => $available,
                'sold_30d' => round($sold, 2),
                'daily_avg' => round($daily, 4),
                'target_qty' => round($target, 2),
                'suggested_qty' => $suggested,
                'supplier_id' => $supplier['supplier_id'] ?? null,
                'supplier_name' => $supplier['supplier_name'] ?? null,
                'unit_price' => round((float) $unitPrice, 4),
                'estimated_amount' => round($suggested * (float) $unitPrice, 2),
            ];
        }

        usort($items, fn ($a, $b) => $a['available'] <=> $b['available'] ?: $b['sold_30d'] <=> $a['sold_30d']);
        $items = array_slice($items, 0, max(1, $limit));

        return [
            'date' => $asOf->toDateString(),
            'threshold' => $threshold,
            'cover_days' => $coverDays,
            'warehouse_id' => $warehouse?->id,
            'warehouse_name' => $warehouse?->name,
            'items' => $items,
            'total_items' => count($items),
            'estimated_amount' => round(array_sum(array_column($items, 'estimated_amount')), 2),
            'items_without_supplier' => count(array_filter($items, fn ($item) => $item['supplier_id'] === null)),
        ];
    }

    /**
     * 应收催收：只列已逾期且仍有余额的发票，按客户汇总。
     */
    public function dunning(?Carbon $asOf = null, int $limit = 15): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();

        $invoices = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIAL])
            ->where('total_amount', '>', 0)
            ->with('partner:id,code,name')
            ->orderBy('due_date')
            ->get();

        $overdue = $invoices->filter(function (Invoice $invoice) use ($asOf) {
            return $invoice->balance() > 0
                && $invoice->due_date
                && $invoice->due_date->lt($asOf);
        });

        $rows = $overdue
            ->groupBy(fn (Invoice $invoice) => $invoice->partner_id ?? 0)
            ->map(function (Collection $group) use ($asOf) {
                $first = $group->first();
                $oldest = $group->sortBy('due_date')->first();

                return [
                    'customer_id' => $first->partner_id,
                    'customer_code' => $first->partner?->code,
                    'customer_name' => $first->partner?->name ?? '未关联客户',
                    'total_balance' => round($group->sum(fn (Invoice $i) => $i->balance()), 2),
                    'invoice_count' => $group->count(),
                    'oldest_due_date' => $oldest?->due_date?->toDateString(),
                    'max_overdue_days' => $oldest?->due_date ? (int) $oldest->due_date->diffInDays($asOf) : 0,
                    'invoices' => $group->sortBy('due_date')->take(5)->map(fn (Invoice $i) => [
                        'code' => $i->code,
                        'due_date' => $i->due_date?->toDateString(),
                        'balance' => round($i->balance(), 2),
                        'overdue_days' => $i->due_date ? (int) $i->due_date->diffInDays($asOf) : 0,
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('total_balance')
            ->values();

        $notDueTotal = round($invoices->filter(fn (Invoice $i) => !$i->due_date || $i->due_date->gte($asOf))
            ->sum(fn (Invoice $i) => $i->balance()), 2);

        $rows = $rows->take(max(1, $limit))->values();

        return [
            'date' => $asOf->toDateString(),
            'customers' => $rows->all(),
            'overdue_total' => round($rows->sum('total_balance'), 2),
            'overdue_invoice_count' => $rows->sum('invoice_count'),
            'not_due_total' => $notDueTotal,
        ];
    }

    /**
     * 周销售分析：本周与上周对比、畅销商品、品类结构、低毛利预警。
     */
    public function weeklySales(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $currentStart = $asOf->copy()->subDays(6);
        $previousStart = $asOf->copy()->subDays(13);
        $previousEnd = $asOf->copy()->subDays(7)->endOfDay();

        $current = $this->salesWindow($currentStart, $asOf->copy()->endOfDay());
        $previous = $this->salesWindow($previousStart, $previousEnd);

        $growth = $previous['revenue'] > 0
            ? round(($current['revenue'] - $previous['revenue']) / $previous['revenue'] * 100, 2)
            : null;

        $marginRate = (float) config('ai.automation.low_margin_rate', 0.15);
        $lowMargin = array_values(array_filter($current['items'], function ($item) use ($marginRate) {
            if ($item['revenue'] <= 0) {
                return false;
            }
            $rate = ($item['revenue'] - $item['cost']) / $item['revenue'];
            return $rate < $marginRate;
        }));
        usort($lowMargin, fn ($a, $b) => $a['margin_rate'] <=> $b['margin_rate']);

        return [
            'start' => $currentStart->toDateString(),
            'end' => $asOf->toDateString(),
            'revenue' => $current['revenue'],
            'previous_revenue' => $previous['revenue'],
            'growth_rate' => $growth,
            'pos_revenue' => $current['pos_revenue'],
            'sales_order_revenue' => $current['sales_order_revenue'],
            'order_count' => $current['order_count'],
            'previous_order_count' => $previous['order_count'],
            'top_products' => array_slice($current['items'], 0, 8),
            'categories' => array_slice($current['categories'], 0, 8, true),
            'low_margin' => array_slice($lowMargin, 0, 5),
        ];
    }

    /**
     * 月末结账体检：草稿凭证、负库存、单据余额与状态不一致。
     */
    public function closingHealth(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $period = FiscalPeriod::activeOn($asOf->toDateString())->first();

        $periodStart = $period?->start_date ?? $asOf->copy()->startOfMonth();
        $periodEnd = $period?->end_date ?? $asOf->copy()->endOfMonth();
        $periodLabel = $period?->name ?? ($asOf->format('Y-m').'（未建期间）');

        $draftEntries = AccountEntry::query()
            ->where('status', AccountEntry::STATUS_DRAFT)
            ->whereDate('entry_date', '>=', $periodStart)
            ->whereDate('entry_date', '<=', $periodEnd)
            ->orderBy('entry_date')
            ->get(['id', 'code', 'entry_date', 'description']);

        $unbalanced = AccountEntry::query()
            ->where('status', AccountEntry::STATUS_POSTED)
            ->with('lines')
            ->get()
            ->filter(fn (AccountEntry $entry) => ! $entry->isBalanced())
            ->values();

        $negativeStock = StockLevel::query()
            ->where(function ($query) {
                $query->where('qty', '<', 0)->orWhereRaw('reserved_qty > qty');
            })
            ->with('product:id,code,name')
            ->get();

        $invoiceMismatch = $this->documentMismatch(Invoice::class);
        $billMismatch = $this->documentMismatch(Bill::class);

        $issues = [];
        if (!$period) {
            $issues[] = ['key' => 'missing_period', 'label' => '缺少当前会计期间', 'count' => 1, 'severity' => 'danger', 'samples' => []];
        } elseif (!$period->isClosed() && $draftEntries->count() > 0) {
            $issues[] = [
                'key' => 'draft_entries',
                'label' => '未过账凭证',
                'count' => $draftEntries->count(),
                'severity' => 'danger',
                'samples' => $draftEntries->take(5)->map(fn ($e) => $e->code.' '.$e->description)->all(),
            ];
        }
        if ($unbalanced->count() > 0) {
            $issues[] = [
                'key' => 'unbalanced_entries',
                'label' => '借贷不平衡的已过账凭证',
                'count' => $unbalanced->count(),
                'severity' => 'danger',
                'samples' => $unbalanced->take(5)->map(fn ($e) => $e->code)->all(),
            ];
        }
        if ($negativeStock->count() > 0) {
            $issues[] = [
                'key' => 'negative_stock',
                'label' => '负库存 / 预留超出现存',
                'count' => $negativeStock->count(),
                'severity' => 'warning',
                'samples' => $negativeStock->take(5)->map(fn ($s) => ($s->product?->code ?? '#'.$s->product_id).' '.($s->product?->name ?? ''))->all(),
            ];
        }
        if ($invoiceMismatch > 0) {
            $issues[] = ['key' => 'invoice_status', 'label' => '应收发票金额与状态不一致', 'count' => $invoiceMismatch, 'severity' => 'warning', 'samples' => []];
        }
        if ($billMismatch > 0) {
            $issues[] = ['key' => 'bill_status', 'label' => '应付账单金额与状态不一致', 'count' => $billMismatch, 'severity' => 'warning', 'samples' => []];
        }

        return [
            'date' => $asOf->toDateString(),
            'period' => $periodLabel,
            'period_status' => $period?->status ?? 'missing',
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'issues' => $issues,
            'can_close' => $period !== null && !$period->isClosed() && $draftEntries->count() === 0 && $unbalanced->count() === 0,
        ];
    }

    /**
     * 每日数据质量巡检。
     */
    public function dataQuality(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();

        $missingBarcode = Product::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''))
            ->get(['id', 'code', 'name']);

        $missingPrice = Product::query()->where('is_active', true)
            ->where('list_price', '<=', 0)
            ->get(['id', 'code', 'name']);

        $negativeStock = StockLevel::query()
            ->where(function ($query) {
                $query->where('qty', '<', 0)->orWhereRaw('reserved_qty > qty');
            })
            ->with('product:id,code,name')
            ->get();

        $duplicateBarcodes = Product::query()
            ->whereNotNull('barcode')
            ->where('barcode', '<>', '')
            ->selectRaw('barcode, COUNT(*) as total')
            ->groupBy('barcode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $duplicateCodes = Product::query()
            ->selectRaw('code, COUNT(*) as total')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $expiredLots = Lot::query()
            ->where('status', Lot::STATUS_ACTIVE)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $asOf)
            ->with('product:id,code,name')
            ->get();

        $invoiceMismatch = $this->documentMismatch(Invoice::class);
        $billMismatch = $this->documentMismatch(Bill::class);

        $issues = [];
        $this->pushIssue($issues, 'missing_barcode', '在售商品缺条码', $missingBarcode, fn ($p) => $p->code.' '.$p->name);
        $this->pushIssue($issues, 'missing_price', '在售商品零售价 ≤ 0', $missingPrice, fn ($p) => $p->code.' '.$p->name);
        $this->pushIssue($issues, 'negative_stock', '负库存 / 预留超出现存', $negativeStock, fn ($s) => ($s->product?->code ?? '#'.$s->product_id).' '.($s->product?->name ?? ''));
        $this->pushIssue($issues, 'duplicate_barcode', '重复条码', $duplicateBarcodes, fn ($b) => $b->barcode.'（'.$b->total.' 个商品）');
        $this->pushIssue($issues, 'duplicate_code', '重复商品编码', $duplicateCodes, fn ($c) => $c->code.'（'.$c->total.' 个商品）');
        $this->pushIssue($issues, 'expired_lots', '过期批次仍为启用状态', $expiredLots, fn ($l) => ($l->product?->code ?? '#'.$l->product_id).' 批次 '.$l->lot_no);
        if ($invoiceMismatch > 0) {
            $issues[] = ['key' => 'invoice_status', 'label' => '应收发票余额与状态不一致', 'count' => $invoiceMismatch, 'severity' => 'warning', 'samples' => []];
        }
        if ($billMismatch > 0) {
            $issues[] = ['key' => 'bill_status', 'label' => '应付账单余额与状态不一致', 'count' => $billMismatch, 'severity' => 'warning', 'samples' => []];
        }

        return [
            'date' => $asOf->toDateString(),
            'issues' => $issues,
            'total_issues' => array_sum(array_column($issues, 'count')),
            'critical_issues' => array_sum(array_map(fn ($i) => $i['severity'] === 'danger' ? $i['count'] : 0, $issues)),
        ];
    }

    /**
     * 会计复核助手：在结账体检之外，补三单差异、已发货未开票、已入库未到票、
     * 盘点未完成/未生成凭证、长期未达银行流水。
     */
    public function accountingReview(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $staleDays = max(1, (int) config('ai.automation.bank_stale_days', 7));

        $closing = $this->closingHealth($asOf);
        $issues = $closing['issues'];

        $bills = Bill::query()
            ->where('status', Bill::STATUS_DRAFT)
            ->with('lines')
            ->get();
        $billDifferences = [];
        foreach ($bills as $bill) {
            $result = $this->bills->match($bill);
            if (! $result['matches']) {
                $billDifferences[] = [
                    'bill' => $bill->code,
                    'partner' => $bill->partner?->name,
                    'differences' => count($result['differences']),
                ];
            }
        }
        if ($billDifferences !== []) {
            $issues[] = [
                'key' => 'three_way_mismatch',
                'label' => '草稿账单三单匹配差异',
                'count' => count($billDifferences),
                'severity' => 'danger',
                'samples' => array_map(fn ($row) => $row['bill'].' / '.($row['partner'] ?: '—').' / '.$row['differences'].' 处差异', array_slice($billDifferences, 0, 5)),
            ];
        }

        $shippedWithoutInvoice = SalesOrder::query()
            ->where('status', SalesOrder::STATUS_SHIPPED)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('invoices')
                    ->where('invoices.source_type', 'sales_order')
                    ->whereColumn('invoices.source_id', 'sales_orders.id');
            })
            ->orderBy('order_date')
            ->get(['id', 'code', 'order_date', 'grand_total']);

        if ($shippedWithoutInvoice->count() > 0) {
            $issues[] = [
                'key' => 'shipped_uninvoiced',
                'label' => '已发货未开票销售单',
                'count' => $shippedWithoutInvoice->count(),
                'severity' => 'warning',
                'samples' => $shippedWithoutInvoice->take(5)->map(fn ($o) => $o->code.' / '.$o->order_date?->toDateString().' / ¥'.number_format((float) $o->grand_total, 2))->all(),
            ];
        }

        $receivedWithoutBill = PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('bills')
                    ->where('bills.source_type', 'purchase_order')
                    ->whereColumn('bills.source_id', 'purchase_orders.id');
            })
            ->orderBy('order_date')
            ->get(['id', 'code', 'order_date', 'grand_total']);

        if ($receivedWithoutBill->count() > 0) {
            $issues[] = [
                'key' => 'received_unbilled',
                'label' => '已收货未到票采购单',
                'count' => $receivedWithoutBill->count(),
                'severity' => 'warning',
                'samples' => $receivedWithoutBill->take(5)->map(fn ($o) => $o->code.' / '.$o->order_date?->toDateString().' / ¥'.number_format((float) $o->grand_total, 2))->all(),
            ];
        }

        $staleCounts = StockCount::query()
            ->whereIn('status', [StockCount::STATUS_DRAFT, StockCount::STATUS_COUNTING])
            ->whereDate('count_date', '<', $asOf->toDateString())
            ->orderBy('count_date')
            ->get(['id', 'code', 'count_date', 'status']);

        if ($staleCounts->count() > 0) {
            $issues[] = [
                'key' => 'stale_stock_counts',
                'label' => '跨日未完成盘点单',
                'count' => $staleCounts->count(),
                'severity' => 'warning',
                'samples' => $staleCounts->take(5)->map(fn ($c) => $c->code.' / '.$c->count_date?->toDateString().' / '.$c->status)->all(),
            ];
        }

        $adjustedCountsWithoutEntry = StockCount::query()
            ->where('status', StockCount::STATUS_ADJUSTED)
            ->whereDate('count_date', '>=', $asOf->copy()->subDays(90))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('account_entries')
                    ->where('account_entries.source_type', 'stock_count')
                    ->whereColumn('account_entries.source_id', 'stock_counts.id');
            })
            ->get(['id', 'code', 'count_date']);

        if ($adjustedCountsWithoutEntry->count() > 0) {
            $issues[] = [
                'key' => 'count_without_entry',
                'label' => '盘点调整尚未生成会计凭证',
                'count' => $adjustedCountsWithoutEntry->count(),
                'severity' => 'danger',
                'samples' => $adjustedCountsWithoutEntry->take(5)->map(fn ($c) => $c->code.' / '.$c->count_date?->toDateString())->all(),
            ];
        }

        $staleBank = BankStatement::query()
            ->where('status', BankStatement::STATUS_UNMATCHED)
            ->whereDate('statement_date', '<=', $asOf->copy()->subDays($staleDays))
            ->orderBy('statement_date')
            ->get(['id', 'statement_date', 'direction', 'amount', 'reference']);

        if ($staleBank->count() > 0) {
            $issues[] = [
                'key' => 'stale_bank_unmatched',
                'label' => '超过 '.$staleDays.' 天未勾对的银行流水',
                'count' => $staleBank->count(),
                'severity' => 'warning',
                'samples' => $staleBank->take(5)->map(fn ($s) => $s->statement_date?->toDateString().' / '.$s->direction.' / ¥'.number_format((float) $s->amount, 2).' / '.($s->reference ?: '—'))->all(),
            ];
        }

        return [
            'date' => $asOf->toDateString(),
            'period' => $closing['period'],
            'period_status' => $closing['period_status'],
            'issues' => $issues,
            'issue_count' => count($issues),
            'critical_count' => count(array_filter($issues, fn ($i) => $i['severity'] === 'danger')),
        ];
    }

    /**
     * 每日循环盘点计划：A 类高价值 + 负库存/预留异常 + 临期批次 + 近期盘差 + 当日出入库。
     * 只生成盘点计划，库存仍以账面数展示，必须由仓管实盘后才可能调整。
     */
    public function cycleCountPlan(?Carbon $asOf = null, ?int $limitPerWarehouse = null): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $limit = max(1, $limitPerWarehouse ?? (int) config('ai.automation.cycle_count_max_products', 30));
        $abcShare = min(0.95, max(0.1, (float) config('ai.automation.cycle_count_abc_share', 0.7)));
        $expiryDays = max(1, (int) config('ai.automation.cycle_count_expiry_days', 30));

        $window = $this->salesWindow($asOf->copy()->subDays(29), $asOf->copy()->endOfDay());
        $revenue = collect($window['items'])->pluck('revenue', 'product_id');
        $totalRevenue = (float) $revenue->sum();

        $aClass = [];
        $cumulative = 0.0;
        foreach ($revenue->sortDesc() as $productId => $amount) {
            if ($amount <= 0 || $totalRevenue <= 0) {
                continue;
            }
            $cumulative += (float) $amount;
            $aClass[(int) $productId] = true;
            if ($cumulative >= $totalRevenue * $abcShare) {
                break;
            }
        }

        $expiring = Lot::query()
            ->where('status', Lot::STATUS_ACTIVE)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $asOf->copy()->addDays($expiryDays))
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip();

        $recentAdjustments = StockMovement::query()
            ->where('source_type', 'adjust')
            ->whereDate('movement_date', '>=', $asOf->copy()->subDays(30))
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip();

        $todayMovements = StockMovement::query()
            ->whereDate('movement_date', $asOf)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip();

        $levels = StockLevel::query()
            ->with('product:id,code,name,standard_cost,avg_cost,is_active')
            ->get()
            ->filter(fn (StockLevel $level) => $level->product?->is_active)
            ->groupBy(fn (StockLevel $level) => $level->warehouse_id.'-'.$level->product_id);

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $plans = [];

        foreach ($warehouses as $warehouse) {
            $items = [];

            foreach ($levels as $key => $rows) {
                $first = $rows->first();
                if ((int) $first->warehouse_id !== (int) $warehouse->id) {
                    continue;
                }

                $product = $first->product;
                $qty = round((float) $rows->sum('qty'), 4);
                $reserved = round((float) $rows->sum('reserved_qty'), 4);
                $available = round($qty - $reserved, 4);
                $value = round($qty * (float) ($product->standard_cost ?: $product->avg_cost), 2);

                $reasons = [];
                if ($qty < 0 || $reserved > $qty) {
                    $reasons[] = '负库存或预留超现存量';
                }
                if ($expiring->has($product->id)) {
                    $reasons[] = '存在 '.$expiryDays.' 天内到期批次';
                }
                if ($recentAdjustments->has($product->id)) {
                    $reasons[] = '近 30 天有盘点调整';
                }
                if ($aClass[$product->id] ?? false) {
                    $reasons[] = 'A 类高销量商品';
                }
                if ($todayMovements->has($product->id)) {
                    $reasons[] = '当日有出入库';
                }

                if ($reasons === []) {
                    continue;
                }

                $items[] = [
                    'product_id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'qty' => $qty,
                    'reserved_qty' => $reserved,
                    'available_qty' => $available,
                    'stock_value' => $value,
                    'revenue_30d' => round((float) ($revenue[$product->id] ?? 0), 2),
                    'reasons' => $reasons,
                    'priority' => count($reasons) * 100 + ($aClass[$product->id] ?? false ? 50 : 0) + min(49, (int) round($value / 100)),
                ];
            }

            usort($items, fn ($a, $b) => $b['priority'] <=> $a['priority'] ?: $b['stock_value'] <=> $a['stock_value']);
            $items = array_slice($items, 0, $limit);

            if ($items !== []) {
                $plans[] = [
                    'warehouse_id' => $warehouse->id,
                    'warehouse_code' => $warehouse->code,
                    'warehouse_name' => $warehouse->name,
                    'products' => $items,
                    'product_count' => count($items),
                    'estimated_stock_value' => round(array_sum(array_column($items, 'stock_value')), 2),
                ];
            }
        }

        return [
            'date' => $asOf->toDateString(),
            'abc_share' => $abcShare,
            'expiry_days' => $expiryDays,
            'warehouses' => $plans,
            'total_products' => array_sum(array_column($plans, 'product_count')),
        ];
    }

    /**
     * 按循环盘点计划创建盘点草稿（不调整库存、不过账）。
     *
     * @return array{created: array, skipped: array}
     */
    public function createCycleCounts(User $user, array $plan): array
    {
        $created = [];
        $skipped = [];

        foreach ($plan['warehouses'] as $warehouse) {
            $warehouseId = (int) $warehouse['warehouse_id'];
            $productIds = array_column($warehouse['products'], 'product_id');

            $exists = StockCount::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('status', [StockCount::STATUS_DRAFT, StockCount::STATUS_COUNTING])
                ->whereDate('count_date', $plan['date'])
                ->exists();

            if ($exists) {
                $skipped[] = [
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouse['warehouse_name'],
                    'reason' => '今日已有未完成盘点单',
                ];
                continue;
            }

            $count = $this->counts->createCount([
                'warehouse_id' => $warehouseId,
                'count_date' => $plan['date'],
                'operator_id' => $user->id,
                'note' => 'AI 每日循环盘点：A 类/负库存/临期/近期盘差/当日变动',
                'product_ids' => $productIds,
            ]);

            $created[] = [
                'count_id' => $count->id,
                'code' => $count->code,
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouse['warehouse_name'],
                'line_count' => $count->lines->count(),
                'product_count' => count($productIds),
            ];
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * 生成低库存采购草稿提案（每个供应商一张，最多 N 行）。
     *
     * @return array{created: array, skipped: array}
     */
    public function createPurchaseProposals(User $user, array $replenishment, ?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $ttl = (int) config('ai.automation.proposal_ttl_minutes', 4320);
        $maxLines = max(1, (int) config('ai.automation.max_purchase_lines', 5));
        $warehouseId = $replenishment['warehouse_id'] ?? Warehouse::query()->where('is_active', true)->value('id');

        if (!$warehouseId) {
            return ['created' => [], 'skipped' => [['supplier_id' => 0, 'supplier_name' => '—', 'reason' => '没有可用仓库']]];
        }

        $groups = [];
        foreach ($replenishment['items'] as $item) {
            if (empty($item['supplier_id']) || $item['suggested_qty'] <= 0 || $item['unit_price'] <= 0) {
                continue;
            }
            $groups[$item['supplier_id']][] = $item;
        }

        $created = [];
        $skipped = [];

        foreach ($groups as $supplierId => $items) {
            $supplierName = $items[0]['supplier_name'] ?? ('供应商 #'.$supplierId);
            $reason = 'AI自动补货建议 '.$asOf->toDateString().' / '.$supplierName;

            $exists = AiChangeProposal::query()
                ->where('user_id', $user->id)
                ->where('resource_type', AiChangeProposal::RESOURCE_PURCHASE_ORDER)
                ->where('status', AiChangeProposal::STATUS_PENDING)
                ->where('reason', $reason)
                ->exists();

            if ($exists) {
                $skipped[] = ['supplier_id' => $supplierId, 'supplier_name' => $supplierName, 'reason' => '今日已有待确认提案'];
                continue;
            }

            $lines = array_map(fn ($item) => [
                'product_id' => $item['product_id'],
                'qty' => $item['suggested_qty'],
                'unit_price' => $item['unit_price'],
            ], array_slice($items, 0, $maxLines));

            $proposal = $this->proposals->propose(
                $user,
                null,
                AiChangeProposal::RESOURCE_PURCHASE_ORDER,
                'create',
                [
                    'supplier_id' => $supplierId,
                    'warehouse_id' => $warehouseId,
                    'expected_date' => $asOf->copy()->addDays(7)->toDateString(),
                    'note' => 'AI 依据近 30 天销量与可用库存生成，确认后仅创建采购草稿。',
                    'lines' => $lines,
                ],
                $reason,
                null,
                $ttl,
            );

            $created[] = [
                'proposal_id' => $proposal->id,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'line_count' => count($lines),
                'expires_at' => $proposal->expires_at?->toDateTimeString(),
            ];
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * 生成低于成本的改价提案；只提案，不直接改价。
     *
     * @return array{created: array, skipped: int}
     */
    public function createPriceProposals(User $user, ?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $ttl = (int) config('ai.automation.proposal_ttl_minutes', 4320);
        $limit = max(1, (int) config('ai.automation.max_price_proposals', 5));

        $products = Product::query()
            ->where('is_active', true)
            ->where('avg_cost', '>', 0)
            ->where('list_price', '<', 'avg_cost')
            ->orderBy('list_price')
            ->limit(50)
            ->get();

        $created = [];
        $skipped = 0;

        foreach ($products as $product) {
            if (count($created) >= $limit) {
                break;
            }

            $reason = 'AI自动改价：售价低于成本 '.$asOf->toDateString();

            $exists = AiChangeProposal::query()
                ->where('user_id', $user->id)
                ->where('resource_type', AiChangeProposal::RESOURCE_PRODUCT)
                ->where('resource_id', $product->id)
                ->where('status', AiChangeProposal::STATUS_PENDING)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $proposed = round((float) $product->avg_cost * 1.15, 2);
            $proposal = $this->proposals->propose(
                $user,
                null,
                AiChangeProposal::RESOURCE_PRODUCT,
                'update_price',
                ['list_price' => $proposed],
                $reason,
                $product,
                $ttl,
            );

            $created[] = [
                'proposal_id' => $proposal->id,
                'product_id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'current_price' => (float) $product->list_price,
                'avg_cost' => (float) $product->avg_cost,
                'proposed_price' => $proposed,
                'expires_at' => $proposal->expires_at?->toDateTimeString(),
            ];
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * 可选 AI 摘要：只改写表达，不改动数字；失败时回退到确定性报告。
     */
    public function narrate(string $task, string $report): string
    {
        if (!$this->ai->isAvailable()) {
            return $report;
        }

        $answer = $this->ai->chat(
            "定时任务：{$task}\n\n以下是系统计算出的真实数据：\n".mb_substr($report, 0, 8000),
            '你是 ERP 运营分析助手。只能使用给定数据，不得改动、补充或编造任何数字。'
            .'请输出简洁中文：先给结论，再给 3 条可执行行动建议，总字数不超过 400 字。',
        );

        return $answer && trim($answer) !== '' ? $answer : $report;
    }

    private function salesWindow(Carbon $start, Carbon $end): array
    {
        $posRevenue = (float) PosOrder::query()
            ->where('status', PosOrder::STATUS_COMPLETED)
            ->whereBetween('order_date', [$start, $end])
            ->sum('grand_total');

        $salesRevenue = (float) SalesOrder::query()
            ->where('status', SalesOrder::STATUS_SHIPPED)
            ->whereBetween('order_date', [$start, $end])
            ->sum('grand_total');

        $orderCount = PosOrder::query()
            ->where('status', PosOrder::STATUS_COMPLETED)
            ->whereBetween('order_date', [$start, $end])
            ->count()
            + SalesOrder::query()
                ->where('status', SalesOrder::STATUS_SHIPPED)
                ->whereBetween('order_date', [$start, $end])
                ->count();

        $posLines = PosOrderLine::query()
            ->whereHas('order', fn ($q) => $q->where('status', PosOrder::STATUS_COMPLETED)->whereBetween('order_date', [$start, $end]))
            ->with('product:id,code,name,category_id,avg_cost')
            ->get();

        $salesLines = SalesOrderLine::query()
            ->whereHas('order', fn ($q) => $q->where('status', SalesOrder::STATUS_SHIPPED)->whereBetween('order_date', [$start, $end]))
            ->with('product:id,code,name,category_id,avg_cost')
            ->get();

        $items = [];
        foreach ($posLines as $line) {
            $product = $line->product;
            if (!$product) {
                continue;
            }
            $revenue = max(0.0, (float) $line->qty * (float) $line->unit_price - (float) $line->discount_amount);
            $this->accumulateItem($items, $product, (float) $line->qty, $revenue);
        }
        foreach ($salesLines as $line) {
            $product = $line->product;
            if (!$product) {
                continue;
            }
            $revenue = max(0.0, (float) $line->subtotal);
            $this->accumulateItem($items, $product, (float) $line->qty, $revenue);
        }

        usort($items, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $categories = [];
        foreach ($items as $item) {
            $name = $item['category'] ?: '未分类';
            $categories[$name] = round(($categories[$name] ?? 0) + $item['revenue'], 2);
        }
        arsort($categories);

        return [
            'revenue' => round($posRevenue + $salesRevenue, 2),
            'pos_revenue' => round($posRevenue, 2),
            'sales_order_revenue' => round($salesRevenue, 2),
            'order_count' => $orderCount,
            'items' => $items,
            'categories' => $categories,
        ];
    }

    private function accumulateItem(array &$items, Product $product, float $qty, float $revenue): void
    {
        $id = $product->id;
        if (!isset($items[$id])) {
            $items[$id] = [
                'product_id' => $id,
                'code' => $product->code,
                'name' => $product->name,
                'category' => $product->category?->name,
                'qty' => 0.0,
                'revenue' => 0.0,
                'cost' => 0.0,
                'margin_rate' => 0.0,
            ];
        }

        $items[$id]['qty'] = round($items[$id]['qty'] + $qty, 2);
        $items[$id]['revenue'] = round($items[$id]['revenue'] + $revenue, 2);
        $items[$id]['cost'] = round($items[$id]['cost'] + $qty * (float) $product->avg_cost, 2);
        $items[$id]['margin_rate'] = $items[$id]['revenue'] > 0
            ? round(($items[$id]['revenue'] - $items[$id]['cost']) / $items[$id]['revenue'], 4)
            : 0.0;

        $items[$id] = array_values($items[$id]);
        $items[$id] = array_combine([
            'product_id', 'code', 'name', 'category', 'qty', 'revenue', 'cost', 'margin_rate',
        ], $items[$id]);
    }

    private function latestSupplier(int $productId): ?array
    {
        $line = PurchaseOrderLine::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_order_lines.product_id', $productId)
            ->whereNotIn('purchase_orders.status', [PurchaseOrder::STATUS_CANCELLED])
            ->orderByDesc('purchase_orders.order_date')
            ->orderByDesc('purchase_order_lines.id')
            ->select([
                'purchase_orders.supplier_id',
                'suppliers.name as supplier_name',
                'purchase_order_lines.unit_price',
            ])
            ->first();

        if (!$line) {
            return null;
        }

        return [
            'supplier_id' => (int) $line->supplier_id,
            'supplier_name' => $line->supplier_name,
            'unit_price' => (float) $line->unit_price,
        ];
    }

    private function documentMismatch(string $model): int
    {
        return $model::query()
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereIn('status', ['open', 'partial'])->whereRaw('paid_amount >= total_amount');
                })->orWhere(function ($q) {
                    $q->where('status', 'paid')->whereRaw('paid_amount < total_amount');
                });
            })
            ->count();
    }

    private function pushIssue(array &$issues, string $key, string $label, Collection $rows, callable $sample): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $issues[] = [
            'key' => $key,
            'label' => $label,
            'count' => $rows->count(),
            'severity' => in_array($key, ['negative_stock', 'duplicate_barcode', 'duplicate_code'], true) ? 'danger' : 'warning',
            'samples' => $rows->take(5)->map($sample)->values()->all(),
        ];
    }
}
