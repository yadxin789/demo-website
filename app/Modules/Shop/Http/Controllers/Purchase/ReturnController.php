<?php

namespace App\Modules\Shop\Http\Controllers\Purchase;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\PurchaseReturn;
use App\Modules\Shop\Models\Receipt;
use App\Modules\Shop\Services\Purchase\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function __construct(protected ReturnService $returns)
    {
    }

    public function index(): View
    {
        $returns = PurchaseReturn::with(['supplier', 'warehouse', 'operator'])
            ->withCount('lines')
            ->orderByDesc('id')
            ->paginate(20);

        return view('shop::purchase.returns.index', compact('returns'));
    }

    public function create(Request $request): View
    {
        $receiptId = (int) $request->query('receipt', 0);

        if ($receiptId === 0) {
            $receipts = Receipt::with(['supplier', 'warehouse', 'lines.product'])
                ->where('status', Receipt::STATUS_DONE)
                ->orderByDesc('id')
                ->get();

            return view('shop::purchase.returns.select', compact('receipts'));
        }

        $receipt = Receipt::with(['lines.product', 'supplier', 'warehouse'])->findOrFail($receiptId);

        $returned = \App\Modules\Shop\Models\PurchaseReturnLine::query()
            ->whereHas('purchaseReturn', fn ($query) => $query->where('status', PurchaseReturn::STATUS_DONE))
            ->selectRaw('receipt_line_id, SUM(qty) as total')
            ->groupBy('receipt_line_id')
            ->pluck('total', 'receipt_line_id');

        return view('shop::purchase.returns.create', compact('receipt', 'returned'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'receipt_id' => ['required', 'exists:receipts,id'],
            'return_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.receipt_line_id' => ['required', 'exists:receipt_lines,id'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.location_id' => ['nullable', 'exists:locations,id'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:50'],
            'lines.*.serial_no' => ['nullable', 'string', 'max:50'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $return = $this->returns->createReturn(
                array_merge($validated, ['operator_id' => auth()->id()]),
                $validated['lines']
            );
        } catch (InventoryException $exception) {
            return redirect()->route('shop.purchase-returns.create', ['receipt' => $validated['receipt_id']])
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.purchase-returns.show', ['purchaseReturn' => $return->id])
            ->with('status', "退货单 {$return->code} 已创建草稿，请核对后过账");
    }

    public function show(PurchaseReturn $purchaseReturn): View
    {
        $purchaseReturn->load(['lines.product', 'lines.location', 'receipt', 'supplier', 'warehouse', 'operator']);

        return view('shop::purchase.returns.show', ['purchaseReturn' => $purchaseReturn]);
    }

    public function post(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        try {
            $this->returns->postReturn($purchaseReturn);
        } catch (InventoryException $exception) {
            return redirect()->route('shop.purchase-returns.show', ['purchaseReturn' => $purchaseReturn->id])
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.purchase-returns.show', ['purchaseReturn' => $purchaseReturn->id])
            ->with('status', "退货单 {$purchaseReturn->code} 已过账，库存已扣减并冲回应付");
    }
}
