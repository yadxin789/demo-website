<?php

namespace App\Modules\Shop\Http\Controllers\Warehouse;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\StockMovement;
use App\Modules\Shop\Models\Warehouse;
use App\Modules\Shop\Services\Inventory\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 上架 / 库位调拨：扫描商品与库位完成同仓库位转移。
 */
class PutawayController extends Controller
{
    public function __construct(protected TransferService $transfers)
    {
    }

    public function index(): View
    {
        $warehouses = Warehouse::query()->orderBy('code')->get();
        $locations = Location::query()->with('warehouse')
            ->where('is_active', true)->orderBy('warehouse_id')->orderBy('code')->get();
        $products = Product::query()->where('is_active', true)
            ->orderBy('code')->get(['id', 'code', 'name', 'barcode', 'traceability', 'standard_cost']);
        $movements = StockMovement::query()->with(['product', 'location'])
            ->where('source_type', 'putaway')
            ->where('direction', StockMovement::DIRECTION_IN)
            ->latest('id')->limit(15)->get();

        return view('shop::warehouse.putaway.index', compact('warehouses', 'locations', 'products', 'movements'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'from_location_id' => ['required', 'integer', 'exists:locations,id', 'different:to_location_id'],
            'to_location_id' => ['required', 'integer', 'exists:locations,id'],
            'qty' => ['required', 'numeric', 'min:0.0001'],
            'lot_no' => ['nullable', 'string', 'max:50'],
            'serial_no' => ['nullable', 'string', 'max:50'],
            'movement_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->transfers->transfer(array_merge($data, [
                'source_type' => 'putaway',
                'source_code' => 'PUT-' . now()->format('YmdHis'),
                'operator_id' => $request->user()?->id,
            ]));
        } catch (\Throwable $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.warehouse.putaway.index')->with('status', '上架完成，库存已转移到目标库位');
    }
}
