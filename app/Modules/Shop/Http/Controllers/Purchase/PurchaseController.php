<?php

namespace App\Modules\Shop\Http\Controllers\Purchase;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Purchase\PurchaseOrderRequest;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\Supplier;
use App\Modules\Shop\Models\Tax;
use App\Modules\Shop\Models\Warehouse;
use App\Modules\Shop\Services\Purchase\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $orders)
    {
    }

    public function index(): View
    {
        $orders = PurchaseOrder::with(['supplier', 'warehouse'])
            ->withCount('lines')
            ->orderByDesc('id')
            ->paginate(20);

        return view('shop::purchase.orders.index', compact('orders'));
    }

    public function create(): View
    {
        [$suppliers, $warehouses, $products, $taxes] = $this->lookups();

        return view('shop::purchase.orders.create', compact('suppliers', 'warehouses', 'products', 'taxes'));
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $order = $this->orders->createOrder(
            array_merge($validated, ['creator_id' => auth()->id()]),
            $validated['lines']
        );

        return redirect()->route('shop.purchase-orders.show', $order)->with('status', "采购单 {$order->code} 已创建");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['lines.product', 'supplier', 'warehouse', 'creator']);

        return view('shop::purchase.orders.show', ['order' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if (! $purchaseOrder->isEditable()) {
            return redirect()->route('shop.purchase-orders.show', $purchaseOrder)
                ->with('error', '仅草稿状态的采购单可以编辑');
        }

        [$suppliers, $warehouses, $products, $taxes] = $this->lookups();
        $purchaseOrder->load('lines.product');

        return view('shop::purchase.orders.edit', compact('purchaseOrder', 'suppliers', 'warehouses', 'products', 'taxes'));
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->orders->updateOrder($purchaseOrder, $validated, $validated['lines']);
        } catch (InventoryException $exception) {
            return redirect()->route('shop.purchase-orders.show', $purchaseOrder)->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.purchase-orders.show', $purchaseOrder)->with('status', '采购单已更新');
    }

    public function transition(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $status = (string) $request->input('status');

        try {
            $this->orders->transition($purchaseOrder, $status, auth()->id());
        } catch (InventoryException $exception) {
            return redirect()->route('shop.purchase-orders.show', $purchaseOrder)->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.purchase-orders.show', $purchaseOrder)->with('status', "采购单已流转至 {$status}");
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->isEditable()) {
            return redirect()->route('shop.purchase-orders.index')->with('error', '仅草稿状态的采购单可以删除');
        }

        $purchaseOrder->lines()->delete();
        $purchaseOrder->delete();

        return redirect()->route('shop.purchase-orders.index')->with('status', '采购单已删除');
    }

    /**
     * @return array{0: \Illuminate\Database\Eloquent\Collection, 1: \Illuminate\Database\Eloquent\Collection, 2: \Illuminate\Database\Eloquent\Collection, 3: \Illuminate\Database\Eloquent\Collection}
     */
    private function lookups(): array
    {
        return [
            Supplier::active()->orderBy('name')->get(),
            Warehouse::orderBy('code')->get(),
            Product::active()->orderBy('code')->get(),
            Tax::active()->orderBy('code')->get(),
        ];
    }
}
