<?php

namespace App\Modules\Shop\Http\Controllers\Purchase;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\Receipt;
use App\Modules\Shop\Models\ReceiptLine;
use App\Modules\Shop\Services\Purchase\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __construct(protected ReceiptService $receipts)
    {
    }

    public function index(): View
    {
        $receipts = Receipt::with(['purchaseOrder', 'supplier', 'warehouse', 'operator'])
            ->withCount('lines')
            ->orderByDesc('id')
            ->paginate(20);

        return view('shop::purchase.receipts.index', compact('receipts'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $poId = (int) $request->query('purchase_order', 0);

        // 到货单必须挂在一张采购订单上：直接打开无参链接时回采购单列表，而不是抛 404
        if ($poId <= 0 && ! PurchaseOrder::whereKey($poId)->exists()) {
            return redirect()
                ->route('shop.purchase-orders.index')
                ->with('info', '请从采购订单列表选择要收货的单据，再进入到货登记。');
        }

        $purchaseOrder = PurchaseOrder::with('lines.product')->findOrFail($poId);
        $locations = Location::where('warehouse_id', $purchaseOrder->warehouse_id)->orderBy('code')->get();

        $received = ReceiptLine::whereHas('receipt', fn ($query) => $query->where('status', Receipt::STATUS_DONE))
            ->whereIn('purchase_order_line_id', $purchaseOrder->lines->pluck('id'))
            ->selectRaw('purchase_order_line_id, SUM(qty) as total')
            ->groupBy('purchase_order_line_id')
            ->pluck('total', 'purchase_order_line_id');

        return view('shop::purchase.receipts.create', compact('purchaseOrder', 'locations', 'received'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'receipt_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'exists:purchase_order_lines,id'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.location_id' => ['nullable', 'exists:locations,id'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:50'],
            'lines.*.serials' => ['nullable'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_note' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['lines'] = $this->normalizeSerialLines($validated['lines']);

        try {
            $receipt = $this->receipts->receive(
                array_merge($validated, ['operator_id' => auth()->id()]),
                $validated['lines']
            );
        } catch (InventoryException $exception) {
            return redirect()->route('shop.purchase-orders.show', ['purchaseOrder' => $validated['purchase_order_id']])
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.receipts.show', ['receipt' => $receipt->id])
            ->with('status', "收货单 {$receipt->code} 已入库");
    }

    public function show(Receipt $receipt): View
    {
        $receipt->load(['lines.product', 'purchaseOrder', 'supplier', 'warehouse', 'operator']);

        return view('shop::purchase.receipts.show', compact('receipt'));
    }

    /**
     * 序列号支持两种录入方式：前端 textarea 换行/逗号分隔，或 API 直接传数组。
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSerialLines(array $lines): array
    {
        foreach ($lines as $index => $line) {
            $raw = $line['serials'] ?? [];

            if (is_string($raw)) {
                $raw = preg_split('/[\s,，、]+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }

            if (! is_array($raw)) {
                unset($lines[$index]['serials']);
                continue;
            }

            $serials = collect($raw)
                ->map(fn ($serial) => trim((string) $serial))
                ->filter()
                ->values()
                ->all();

            $lines[$index]['serials'] = $serials;
        }

        return array_values($lines);
    }
}
