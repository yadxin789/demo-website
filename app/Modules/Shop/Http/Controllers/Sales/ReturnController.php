<?php

namespace App\Modules\Shop\Http\Controllers\Sales;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\SalesReturn;
use App\Modules\Shop\Services\Sales\ReturnService;
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
        $returns = SalesReturn::with(['customer', 'warehouse', 'operator'])
            ->withCount('lines')
            ->orderByDesc('id')
            ->paginate(20);

        return view('shop::sales.returns.index', compact('returns'));
    }

    public function create(Request $request): View
    {
        $source = (string) $request->query('source', '');
        $orderId = (int) $request->query('id', 0);

        if ($source === '' || $orderId === 0) {
            $salesOrders = SalesOrder::where('status', SalesOrder::STATUS_SHIPPED)
                ->with('customer', 'lines.product')
                ->orderByDesc('id')
                ->get();
            $posOrders = PosOrder::where('status', PosOrder::STATUS_COMPLETED)
                ->with('customer', 'lines.product')
                ->orderByDesc('id')
                ->get();

            return view('shop::sales.returns.select', compact('salesOrders', 'posOrders'));
        }

        if ($source === SalesReturn::SOURCE_SALES_ORDER) {
            $order = SalesOrder::with('lines.product', 'customer')->findOrFail($orderId);
        } else {
            $order = PosOrder::with('lines.product', 'customer')->findOrFail($orderId);
        }

        $returned = \App\Modules\Shop\Models\SalesReturnLine::query()
            ->whereHas('salesReturn', fn ($query) => $query->where('status', SalesReturn::STATUS_DONE))
            ->get()
            ->groupBy(fn ($line) => $line->sales_order_line_id ?? $line->pos_order_line_id)
            ->map(fn ($group) => (float) $group->sum('qty'));

        return view('shop::sales.returns.create', compact('source', 'order', 'returned'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_type' => ['required', 'in:sales_order,pos_order'],
            'sales_order_id' => ['nullable', 'required_if:source_type,sales_order', 'exists:sales_orders,id'],
            'pos_order_id' => ['nullable', 'required_if:source_type,pos_order', 'exists:pos_orders,id'],
            'return_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'refund_method' => ['nullable', 'in:credit,cash,bank'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['nullable', 'exists:sales_order_lines,id'],
            'lines.*.pos_order_line_id' => ['nullable', 'exists:pos_order_lines,id'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.location_id' => ['nullable', 'exists:locations,id'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:50'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $return = $this->returns->createReturn(
                array_merge($validated, ['operator_id' => auth()->id()]),
                $validated['lines']
            );
        } catch (InventoryException $exception) {
            $source = $validated['source_type'];
            $orderId = $source === SalesReturn::SOURCE_SALES_ORDER
                ? ($validated['sales_order_id'] ?? 0)
                : ($validated['pos_order_id'] ?? 0);

            return redirect()->route('shop.sales-returns.create', ['source' => $source, 'id' => $orderId])
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.sales-returns.show', ['salesReturn' => $return->id])
            ->with('status', "退货单 {$return->code} 已创建草稿，请核对后过账");
    }

    public function show(SalesReturn $salesReturn): View
    {
        $salesReturn->load(['lines.product', 'customer', 'warehouse', 'operator', 'salesOrder', 'posOrder']);

        return view('shop::sales.returns.show', ['salesReturn' => $salesReturn]);
    }

    public function post(SalesReturn $salesReturn): RedirectResponse
    {
        try {
            $this->returns->postReturn($salesReturn);
        } catch (InventoryException $exception) {
            return redirect()->route('shop.sales-returns.show', ['salesReturn' => $salesReturn->id])
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.sales-returns.show', ['salesReturn' => $salesReturn->id])
            ->with('status', "退货单 {$salesReturn->code} 已过账，商品回库并冲回收入与成本");
    }
}
