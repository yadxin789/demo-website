<?php

namespace App\Modules\Shop\Http\Controllers\Pos;

use App\Modules\Shop\Exceptions\PosException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Customer;
use App\Modules\Shop\Models\PaymentMethod;
use App\Modules\Shop\Models\PosSession;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Services\Inventory\StockService;
use App\Modules\Shop\Services\Pos\CartService;
use App\Modules\Shop\Services\Pos\CheckoutService;
use App\Modules\Shop\Services\Pos\SavedOrderService;
use App\Modules\Shop\Services\Pos\ShiftService;
use App\Modules\Shop\Support\ScanCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $session = PosSession::where('cashier_id', auth()->id())
            ->where('status', PosSession::STATUS_OPEN)
            ->first();

        $products = Product::where('is_active', true)
            ->orderBy('code')
            ->limit(60)
            ->get(['id', 'code', 'name', 'barcode', 'list_price', 'traceability']);

        // 终端级开关：结账后是否自动调起浏览器打印对话框（随当班终端配置）。
        $autoPrintReceipt = (bool) ($session?->station?->auto_print_receipt ?? false);

        return view('shop::pos.index', compact('session', 'products', 'autoPrintReceipt'));
    }

    /**
     * 商品检索：条码/编码精确优先，名称/编码/条码模糊匹配。
     */
    public function search(Request $request, CartService $cart): JsonResponse
    {
        $products = $cart->search((string) $request->query('q', ''), 30)->map(fn (Product $p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'barcode' => $p->barcode,
            'list_price' => (float) $p->list_price,
            'traceability' => $p->traceability,
        ]);

        return response()->json(['products' => $products]);
    }

    /**
     * 加购行快照：校验在售商品并取零售价、税额与追踪方式。
     */
    public function addLine(Request $request, CartService $cart, StockService $stock): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'qty' => 'nullable|numeric|min:0.0001',
            'lot_no' => 'nullable|string|max:64',
            'serial_no' => 'nullable|string|max:64',
        ]);

        $product = Product::active()->findOrFail($data['product_id']);
        $qty = (float) ($data['qty'] ?? 1);
        $lotNo = isset($data['lot_no']) ? trim((string) $data['lot_no']) : null;
        $serialNo = isset($data['serial_no']) ? trim((string) $data['serial_no']) : null;

        // 追踪商品必须指定批次/序列号，否则 POS 无法定向出库。
        if ($product->traceability === CartService::TRACE_LOT && ($lotNo === null || $lotNo === '')) {
            throw new PosException("商品 {$product->code} 为批次追踪，请先选择批次");
        }
        if ($product->traceability === CartService::TRACE_SERIAL) {
            if ($serialNo === null || $serialNo === '') {
                throw new PosException("商品 {$product->code} 为序列号追踪，请先选择序列号");
            }
            if ($qty != 1) {
                throw new PosException("商品 {$product->code} 序列号商品一次只能加购一件");
            }
        }

        $session = $this->openSession();
        if ($session && $session->station) {
            $this->assertStockAvailable($stock, $product, $session, $lotNo, $serialNo, $qty);
        }

        $line = $cart->resolveLine($product, $qty, 0, $lotNo, $serialNo);
        $cart->logAddLine($product, $line, $this->sessionContext() + ['operator_id' => auth()->id()]);

        return response()->json(['line' => $line]);
    }

    /**
     * 校验追踪商品在收银台库位下可出库（批次看数量，序列号看单件）。
     */
    private function assertStockAvailable(
        StockService $stock,
        Product $product,
        PosSession $session,
        ?string $lotNo,
        ?string $serialNo,
        float $qty
    ): void {
        $warehouseId = (int) $session->station->warehouse_id;
        $locationId = $session->station->default_location_id
            ? (int) $session->station->default_location_id
            : null;

        if ($product->traceability === CartService::TRACE_LOT) {
            $available = (float) $stock->levels([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'lot_no' => $lotNo,
            ])->filter(fn ($level) => (int) ($level->location_id ?? 0) === (int) ($locationId ?? 0))
              ->sum(fn ($level) => $level->availableQty());

            if ($available + 0.0000001 < $qty) {
                throw new PosException("批次 {$lotNo} 可用库存不足：可用 {$available}，需要 {$qty}");
            }

            return;
        }

        if ($product->traceability === CartService::TRACE_SERIAL) {
            $level = $stock->findLevel($product->id, $warehouseId, $locationId, null, $serialNo);

            if (! $level || $level->availableQty() < 1) {
                throw new PosException("序列号 {$serialNo} 不在当前收银台可售库存中");
            }
        }
    }

    /**
     * 批次选项：当前收银台默认库位下有可用量的批次。
     */
    public function lots(Request $request, StockService $stock): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $session = $this->openSession();
        if (! $session || ! $session->station) {
            return response()->json(['lots' => []]);
        }

        $levels = $stock->levels([
            'product_id' => (int) $data['product_id'],
            'warehouse_id' => (int) $session->station->warehouse_id,
            'location_id' => $session->station->default_location_id
                ? (int) $session->station->default_location_id
                : null,
        ])->filter(fn ($level) => $level->lot_no !== null && $level->availableQty() > 0
                && (int) ($level->location_id ?? 0) === (int) ($session->station->default_location_id ?? 0));

        $lots = $levels->groupBy('lot_no')->map(fn ($group, $lotNo) => [
            'lot_no' => (string) $lotNo,
            'available' => round((float) $group->sum(fn ($level) => $level->availableQty()), 4),
        ])->values();

        return response()->json(['lots' => $lots]);
    }

    /**
     * 序列号选项：当前收银台默认库位下在库可售的序列号。
     */
    public function serials(Request $request, StockService $stock): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $session = $this->openSession();
        if (! $session || ! $session->station) {
            return response()->json(['serials' => []]);
        }

        $levels = $stock->levels([
            'product_id' => (int) $data['product_id'],
            'warehouse_id' => (int) $session->station->warehouse_id,
            'location_id' => $session->station->default_location_id
                ? (int) $session->station->default_location_id
                : null,
        ])->filter(fn ($level) => $level->serial_no !== null && $level->availableQty() > 0
                && (int) ($level->location_id ?? 0) === (int) ($session->station->default_location_id ?? 0))
            ->sortBy('serial_no')
            ->values();

        return response()->json(['serials' => $levels->map(fn ($level) => [
            'serial_no' => $level->serial_no,
        ])->all()]);
    }

    /**
     * 挂账/会员客户检索：编码或名称模糊匹配，最多 20 条。
     */
    public function customers(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->when($query !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "%{$query}%")))
            ->orderBy('code')
            ->limit(20)
            ->get(['id', 'code', 'name', 'default_discount_rate', 'credit_limit'])
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'default_discount_rate' => (float) $c->default_discount_rate,
                'credit_limit' => (float) $c->credit_limit,
            ]);

        return response()->json(['customers' => $customers]);
    }

    /** 当前用户未交班会话（可为空）。 */
    private function openSession(): ?PosSession
    {
        return PosSession::where('cashier_id', auth()->id())
            ->where('status', PosSession::STATUS_OPEN)
            ->first();
    }

    /**
     * 改行数量。
     */
    public function updateQty(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'qty' => 'required|numeric|min:0.0001',
            'lot_no' => 'nullable|string|max:64',
            'serial_no' => 'nullable|string|max:64',
            'lines' => 'array',
        ]);

        $lines = $cart->updateQty(
            $request->input('lines', []),
            (int) $data['product_id'],
            (float) $data['qty'],
            auth()->id(),
            $this->sessionContext(),
            $data['lot_no'] ?? null,
            $data['serial_no'] ?? null
        );

        return response()->json(['cart' => $lines, 'totals' => $cart->totals($lines)]);
    }

    /**
     * 行内改价（需 pos.price.adjust 权限，路由中间件已校验）。
     */
    public function updatePrice(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'unit_price' => 'required|numeric|min:0',
            'lot_no' => 'nullable|string|max:64',
            'serial_no' => 'nullable|string|max:64',
            'lines' => 'array',
        ]);

        $canAdjust = $request->user()->hasPermission('pos.price.adjust')
            || $request->user()->roles()->where('code', 'super-admin')->exists();

        $lines = $cart->updatePrice(
            $request->input('lines', []),
            (int) $data['product_id'],
            (float) $data['unit_price'],
            $canAdjust,
            auth()->id(),
            $this->sessionContext(),
            $data['lot_no'] ?? null,
            $data['serial_no'] ?? null
        );

        return response()->json(['cart' => $lines, 'totals' => $cart->totals($lines)]);
    }

    /**
     * 行折扣率。
     */
    public function applyDiscount(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'discount_rate' => 'required|numeric|min:0|max:100',
            'lot_no' => 'nullable|string|max:64',
            'serial_no' => 'nullable|string|max:64',
            'lines' => 'array',
        ]);

        $lines = $cart->applyLineDiscount(
            $request->input('lines', []),
            (int) $data['product_id'],
            (float) $data['discount_rate'],
            auth()->id(),
            $this->sessionContext(),
            $data['lot_no'] ?? null,
            $data['serial_no'] ?? null
        );

        return response()->json(['cart' => $lines, 'totals' => $cart->totals($lines)]);
    }

    /**
     * 整单折扣：折扣率或折扣额二选一；返回重算后的头合计。
     */
    public function applyOrderDiscount(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'lines' => 'array',
        ]);

        $rate = isset($data['discount_rate']) ? (float) $data['discount_rate'] : 0;
        $amount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : null;

        $totals = $cart->orderTotals($request->input('lines', []), $rate, $amount);
        $cart->logOrderDiscount($rate, $amount, $totals, auth()->id(), $this->sessionContext());

        return response()->json(['totals' => $totals]);
    }

    /**
     * 删行。
     */
    public function removeLine(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'lot_no' => 'nullable|string|max:64',
            'serial_no' => 'nullable|string|max:64',
            'lines' => 'array',
        ]);

        $lines = $cart->removeLine(
            $request->input('lines', []),
            (int) $data['product_id'],
            auth()->id(),
            $this->sessionContext(),
            $data['lot_no'] ?? null,
            $data['serial_no'] ?? null
        );

        return response()->json(['cart' => $lines, 'totals' => $cart->totals($lines)]);
    }

    /**
     * 当前用户未交班会话；未开班拦截。
     */
    private function currentSession(): PosSession
    {
        $session = PosSession::where('cashier_id', auth()->id())
            ->where('status', PosSession::STATUS_OPEN)
            ->first();

        if (! $session) {
            abort(403, '当前收银台尚未开班，不能挂单或取单');
        }

        return $session;
    }

    /**
     * 审计上下文：取当前打开会话的终端与会话号；未开班时返回空数组（不拦截车行编辑）。
     *
     * @return array<string, int>
     */
    private function sessionContext(): array
    {
        $session = PosSession::where('cashier_id', auth()->id())
            ->where('status', PosSession::STATUS_OPEN)
            ->first();

        return $session
            ? ['station_id' => $session->station_id, 'session_id' => $session->id]
            : [];
    }

    /**
     * 有效挂单列表。
     */
    public function listSaved(SavedOrderService $service): JsonResponse
    {
        $session = $this->currentSession();

        $list = $service->listValid($session->station_id, auth()->id())
            ->map(fn ($order) => [
                'id' => $order->id,
                'saved_at' => optional($order->saved_at)->toDateTimeString(),
                'line_count' => count($order->cart_json ?? []),
                'note' => $order->note,
            ]);

        return response()->json(['saved' => $list]);
    }

    /**
     * 保存挂单（不扣库存）。
     */
    public function saveSaved(Request $request, SavedOrderService $service): JsonResponse
    {
        $data = $request->validate([
            'lines' => 'array',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'note' => 'nullable|string',
        ]);

        $session = $this->currentSession();

        $order = $service->save(
            $session->station_id,
            auth()->id(),
            $data['lines'] ?? [],
            $data['customer_id'] ?? null,
            null,
            $data['note'] ?? null
        );

        return response()->json([
            'saved' => ['id' => $order->id, 'line_count' => count($order->cart_json ?? [])],
        ]);
    }

    /**
     * 取单还原购物车。
     */
    public function restoreSaved(int $id, SavedOrderService $service): JsonResponse
    {
        $this->currentSession();

        $result = $service->restore($id, auth()->id());

        return response()->json([
            'cart' => $result['cart'],
            'customer_id' => $result['order']->customer_id,
        ]);
    }

    /**
     * 删除本人挂单。
     */
    public function deleteSaved(int $id, SavedOrderService $service): JsonResponse
    {
        $this->currentSession();

        $service->delete($id, auth()->id());

        return response()->json(['ok' => true]);
    }

    /**
     * 当前终端允许的启用支付方式。
     */
    public function paymentMethods(): JsonResponse
    {
        $session = $this->currentSession();

        $methods = PaymentMethod::active()->get()
            ->filter(fn (PaymentMethod $m) => $session->station->allowsPaymentMethod($m->id))
            ->map(fn (PaymentMethod $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'code' => $m->code,
                'type' => $m->type,
            ])->values();

        return response()->json(['methods' => $methods]);
    }

    /**
     * 生成动态收款二维码（离线登记）：金额与收银会话写入缓存，供扫码端解析。
     */
    public function paymentQr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $session = $this->currentSession();
        $token = strtolower(Str::random(32));
        $expiresAt = now()->addMinutes(10);

        Cache::put('pos_payment_qr:' . $token, [
            'amount' => round((float) $data['amount'], 2),
            'session_id' => $session->id,
            'station_id' => $session->station_id,
            'cashier_id' => $session->cashier_id,
            'created_at' => now()->toDateTimeString(),
            'expires_at' => $expiresAt->toDateTimeString(),
        ], $expiresAt);

        return response()->json([
            'token' => $token,
            'amount' => round((float) $data['amount'], 2),
            'payload' => ScanCode::payment($token),
            'expires_at' => $expiresAt->toDateTimeString(),
        ]);
    }

    /**
     * 当班实时汇总：笔数、销售额、各支付方式、折扣、应收现金。
     */
    public function shiftSummary(ShiftService $service): JsonResponse
    {
        $session = $this->currentSession();

        return response()->json(['summary' => $service->summary($session)]);
    }

    /**
     * 交班清点：登记面额实点现金，计算长短款并锁定会话。
     */
    public function closeShift(Request $request, ShiftService $service): JsonResponse
    {
        $data = $request->validate([
            'denominations' => 'nullable|array',
            'denominations.*.denomination' => 'required|numeric|min:0',
            'denominations.*.count_qty' => 'required|integer|min:0',
            'note' => 'nullable|string',
        ]);

        $session = $this->currentSession();

        $closed = $service->close(
            $session,
            $data['denominations'] ?? [],
            auth()->id(),
            $data['note'] ?? null
        );

        return response()->json([
            'session' => [
                'id' => $closed->id,
                'status' => $closed->status,
                'expected_cash' => (float) $closed->expected_cash,
                'counted_cash' => (float) $closed->counted_cash,
                'variance_amount' => (float) $closed->variance_amount,
            ],
        ]);
    }

    /**
     * 结账：落库订单、行与多笔支付。
     */
    public function checkout(Request $request, CheckoutService $service): JsonResponse
    {
        $data = $request->validate([
            'lines' => 'required|array',
            'payments' => 'required|array',
            'payments.*.method_id' => 'required|integer',
            'payments.*.amount' => 'required|numeric|min:0.0001',
            'payments.*.reference' => 'nullable|string',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $session = $this->currentSession();

        $order = $service->checkout(
            $session,
            $data['lines'],
            $data['payments'],
            $data['customer_id'] ?? null,
            isset($data['discount_rate']) ? (float) $data['discount_rate'] : 0,
            isset($data['discount_amount']) ? (float) $data['discount_amount'] : null,
            $data['note'] ?? null,
            auth()->id()
        );

        return response()->json([
            'order' => [
                'id' => $order->id,
                'code' => $order->code,
                'grand_total' => (float) $order->grand_total,
                'paid_amount' => (float) $order->paid_amount,
                'change_amount' => (float) $order->change_amount,
            ],
            'payments' => $order->payments->map(fn ($p) => [
                'method_id' => $p->method_id,
                'amount' => (float) $p->amount,
                'reference' => $p->reference,
            ]),
        ]);
    }
}
