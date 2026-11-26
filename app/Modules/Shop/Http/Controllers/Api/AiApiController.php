<?php

namespace App\Modules\Shop\Http\Controllers\Api;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\AiChangeProposal;
use App\Modules\Shop\Models\AiDocTemplate;
use App\Modules\Shop\Models\Bill;
use App\Modules\Shop\Models\Customer;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Models\Supplier;
use App\Modules\Shop\Services\AiChangeProposalService;
use App\Modules\Shop\Services\AiDelegationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 供外部 Agent 调用的 ERP 数据 API。
 *
 * 认证：X-AI-Key（系统密钥）
 * 授权：X-AI-Token（用户在 AI 授权中心签发的委托令牌）
 * 写操作永远只创建「变更提案」，必须由授权人确认后才落库。
 */
class AiApiController extends Controller
{
    public function __construct(
        private readonly AiDelegationService $delegation,
        private readonly AiChangeProposalService $proposals,
    ) {
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'system' => 'ERP 进销存与财务系统',
            'version' => '1.0',
            'base_url' => url('shop/api/ai'),
            'auth' => [
                'system_key' => ['header' => 'X-AI-Key', 'query' => 'api_key'],
                'delegation' => ['header' => 'X-AI-Token', 'query' => 'ai_token'],
            ],
            'delegation_note' => '委托令牌不增加任何权限，AI 能做什么完全取决于签发者本人。',
            'endpoints' => [
                ['path' => '/overview', 'method' => 'GET', 'desc' => '经营总览'],
                ['path' => '/products', 'method' => 'GET', 'params' => 'keyword, limit', 'desc' => '商品列表'],
                ['path' => '/inventory', 'method' => 'GET', 'params' => 'keyword, low_stock, limit', 'desc' => '库存汇总'],
                ['path' => '/sales-orders', 'method' => 'GET', 'params' => 'status, days, limit', 'desc' => '销售单'],
                ['path' => '/purchase-orders', 'method' => 'GET', 'params' => 'status, limit', 'desc' => '采购单'],
                ['path' => '/receivables', 'method' => 'GET', 'params' => 'overdue_only, limit', 'desc' => '应收明细'],
                ['path' => '/templates', 'method' => 'GET', 'desc' => 'AI 文档模板'],
                ['path' => '/proposals', 'method' => 'GET', 'desc' => '本令牌用户的变更提案'],
                ['path' => '/proposals/{id}', 'method' => 'GET', 'desc' => '提案详情与差异'],
            ],
            'write_endpoints' => [
                'note' => '两阶段：POST 创建提案拿到 proposal_id 与 diff，再由授权人确认后生效。',
                ['path' => '/write/product-price', 'method' => 'POST', 'desc' => '提交商品改价提案'],
                ['path' => '/write/purchase-order', 'method' => 'POST', 'desc' => '提交采购草稿提案'],
            ],
        ]);
    }

    public function overview(): JsonResponse
    {
        $this->authorizeRead();

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $posToday = (float) PosOrder::where('status', PosOrder::STATUS_COMPLETED)
            ->where('order_date', '>=', $today)->sum('grand_total');
        $salesToday = (float) SalesOrder::whereIn('status', [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_RETURNED])
            ->whereDate('order_date', '>=', $today)->sum('grand_total');

        $posMonth = (float) PosOrder::where('status', PosOrder::STATUS_COMPLETED)
            ->where('order_date', '>=', $monthStart)->sum('grand_total');
        $salesMonth = (float) SalesOrder::whereIn('status', [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_RETURNED])
            ->whereDate('order_date', '>=', $monthStart)->sum('grand_total');

        $lowStock = StockLevel::query()
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

        return response()->json(['data' => [
            'date' => $today->toDateString(),
            'products_active' => Product::where('is_active', true)->count(),
            'low_stock_products' => $lowStock,
            'sales_today' => round($posToday + $salesToday, 2),
            'sales_today_pos' => round($posToday, 2),
            'sales_today_orders' => round($salesToday, 2),
            'sales_month' => round($posMonth + $salesMonth, 2),
            'pending_receipts' => PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIAL])->count(),
            'receivable_balance' => round($receivable, 2),
            'payable_balance' => round($payable, 2),
            'customers_active' => Customer::where('is_active', true)->count(),
            'suppliers_active' => Supplier::where('is_active', true)->count(),
        ]]);
    }

    public function products(Request $request): JsonResponse
    {
        $this->authorizeRead();

        $query = Product::query()->where('is_active', true)->orderBy('code');
        if ($keyword = trim((string) $request->query('keyword'))) {
            $query->where(fn ($q) => $q->where('code', 'like', "%{$keyword}%")
                ->orWhere('name', 'like', "%{$keyword}%")
                ->orWhere('barcode', 'like', "%{$keyword}%"));
        }

        $products = $query->limit($this->limit($request))->get();
        $available = StockLevel::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->selectRaw('product_id, SUM(qty - reserved_qty) as available')
            ->groupBy('product_id')
            ->pluck('available', 'product_id');

        return response()->json(['data' => $products->map(fn (Product $p) => [
            'id' => $p->id,
            'code' => $p->code,
            'barcode' => $p->barcode,
            'name' => $p->name,
            'list_price' => (float) $p->list_price,
            'standard_cost' => (float) $p->standard_cost,
            'available_qty' => (float) ($available[$p->id] ?? 0),
        ])]);
    }

    public function inventory(Request $request): JsonResponse
    {
        $this->authorizeRead();

        $rows = StockLevel::query()
            ->selectRaw('product_id, SUM(qty) as qty, SUM(reserved_qty) as reserved_qty, SUM(qty - reserved_qty) as available')
            ->groupBy('product_id')
            ->orderBy('available');

        if ($request->boolean('low_stock')) {
            $rows->havingRaw('SUM(qty - reserved_qty) <= 10');
        }

        $data = $rows->limit($this->limit($request))->get();
        $products = Product::whereIn('id', $data->pluck('product_id'))->get(['id', 'code', 'name'])->keyBy('id');

        if ($keyword = trim((string) $request->query('keyword'))) {
            $data = $data->filter(function ($row) use ($products, $keyword) {
                $product = $products[$row->product_id] ?? null;
                return $product && (str_contains((string) $product->code, $keyword) || str_contains((string) $product->name, $keyword));
            })->values();
        }

        return response()->json(['data' => $data->map(function ($row) use ($products) {
            $product = $products[$row->product_id] ?? null;
            return [
                'product_id' => (int) $row->product_id,
                'code' => $product?->code,
                'name' => $product?->name,
                'qty' => (float) $row->qty,
                'reserved_qty' => (float) $row->reserved_qty,
                'available_qty' => (float) $row->available,
            ];
        })]);
    }

    public function salesOrders(Request $request): JsonResponse
    {
        $this->authorizeRead();

        $query = SalesOrder::query()->with('customer:id,code,name')->latest('order_date');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        $query->whereDate('order_date', '>=', now()->subDays(max(1, (int) $request->query('days', 30))));

        return response()->json(['data' => $query->limit($this->limit($request))->get()->map(fn (SalesOrder $o) => [
            'id' => $o->id,
            'code' => $o->code,
            'customer' => $o->customer?->name,
            'order_date' => $o->order_date?->toDateString(),
            'status' => $o->status,
            'grand_total' => (float) $o->grand_total,
        ])]);
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $this->authorizeRead();

        $query = PurchaseOrder::query()->with('supplier:id,code,name')->latest('order_date');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->limit($this->limit($request))->get()->map(fn (PurchaseOrder $o) => [
            'id' => $o->id,
            'code' => $o->code,
            'supplier' => $o->supplier?->name,
            'order_date' => $o->order_date?->toDateString(),
            'expected_date' => $o->expected_date?->toDateString(),
            'status' => $o->status,
            'grand_total' => (float) $o->grand_total,
        ])]);
    }

    public function receivables(Request $request): JsonResponse
    {
        $this->authorizeRead();

        $query = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIAL])
            ->whereRaw('(total_amount - paid_amount) > 0')
            ->with('partner:id,code,name')
            ->orderBy('due_date');

        if ($request->boolean('overdue_only')) {
            $query->whereDate('due_date', '<', now()->toDateString());
        }

        return response()->json(['data' => $query->limit($this->limit($request))->get()->map(fn (Invoice $i) => [
            'id' => $i->id,
            'code' => $i->code,
            'customer' => $i->partner?->name,
            'invoice_date' => $i->invoice_date?->toDateString(),
            'due_date' => $i->due_date?->toDateString(),
            'status' => $i->status,
            'balance' => round((float) $i->total_amount - (float) $i->paid_amount, 2),
            'overdue_days' => $i->due_date && $i->due_date->isPast() ? (int) $i->due_date->diffInDays(now()) : 0,
        ])]);
    }

    public function templates(): JsonResponse
    {
        $this->authorizeRead();

        return response()->json(['data' => AiDocTemplate::query()->where('enabled', true)->get()->map(fn (AiDocTemplate $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'category' => $t->category,
            'description' => $t->description,
        ])]);
    }

    public function proposals(Request $request): JsonResponse
    {
        $user = $this->authorizeRead();

        $query = AiChangeProposal::query()->where('user_id', $user->id)->latest();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->limit($this->limit($request))->get()->map(fn (AiChangeProposal $p) => $this->proposalPayload($p))]);
    }

    public function proposalShow(int $id): JsonResponse
    {
        $user = $this->authorizeRead();
        $proposal = AiChangeProposal::find($id);

        if (!$proposal || !$this->delegation->canResolveProposal($user, $proposal)) {
            return response()->json(['error' => '提案不存在或无权查看。'], 404);
        }

        return response()->json(['data' => $this->proposalPayload($proposal)]);
    }

    public function proposePrice(Request $request): JsonResponse
    {
        $user = $this->delegation->requireUser();
        $this->delegation->requireScope('write');

        if (!$this->delegation->canWriteProducts($user)) {
            return response()->json(['error' => '无权提交商品改价提案。'], 403);
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'list_price' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $proposal = $this->proposals->propose(
            $user,
            $this->delegation->token(),
            AiChangeProposal::RESOURCE_PRODUCT,
            'update_price',
            ['list_price' => (float) $data['list_price']],
            $data['reason'] ?? null,
            $product,
        );

        return response()->json([
            'data' => $this->proposalPayload($proposal),
            'confirm_url' => route('shop.ai-delegation.index'),
        ], 201);
    }

    public function proposePurchaseOrder(Request $request): JsonResponse
    {
        $user = $this->delegation->requireUser();
        $this->delegation->requireScope('write');

        if (!$this->delegation->canWritePurchase($user)) {
            return response()->json(['error' => '无权提交采购单提案。'], 403);
        }

        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'expected_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $proposal = $this->proposals->propose(
            $user,
            $this->delegation->token(),
            AiChangeProposal::RESOURCE_PURCHASE_ORDER,
            'create',
            $data,
            $data['note'] ?? null,
        );

        return response()->json([
            'data' => $this->proposalPayload($proposal),
            'confirm_url' => route('shop.ai-delegation.index'),
        ], 201);
    }

    private function authorizeRead(): \App\Modules\Shop\Models\User
    {
        $user = $this->delegation->requireUser();
        $this->delegation->requireScope('read');

        if (!$this->delegation->canReadErp($user)) {
            abort(response()->json(['error' => '无权读取 ERP 数据。'], 403));
        }

        return $user;
    }

    private function limit(Request $request): int
    {
        return min(100, max(1, (int) $request->query('limit', 20)));
    }

    private function proposalPayload(AiChangeProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'resource_type' => $proposal->resource_type,
            'resource_label' => $proposal->resourceLabel(),
            'resource_id' => $proposal->resource_id,
            'action' => $proposal->action,
            'action_label' => $proposal->actionLabel(),
            'status' => $proposal->status,
            'before' => $proposal->before,
            'after' => $proposal->after,
            'reason' => $proposal->reason,
            'expires_at' => $proposal->expires_at?->toIso8601String(),
        ];
    }
}
