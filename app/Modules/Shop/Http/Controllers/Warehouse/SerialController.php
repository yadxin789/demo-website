<?php

namespace App\Modules\Shop\Http\Controllers\Warehouse;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 序列号台账：一货一码，追踪在库 / 已售状态与当前库位、最近单据。
 */
class SerialController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', '');

        $serials = StockLevel::query()
            ->with(['product', 'location', 'latestMovement'])
            ->whereNotNull('serial_no')
            ->when($status === 'on_hand', fn ($query) => $query->where('qty', '>', 0))
            ->when($status === 'sold', fn ($query) => $query->where('qty', '<=', 0))
            ->when($status === 'returned', fn ($query) => $query
                ->where('qty', '>', 0)
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('stock_movements as m')
                        ->whereColumn('m.product_id', 'stock_levels.product_id')
                        ->whereColumn('m.serial_no', 'stock_levels.serial_no')
                        ->where('m.source_type', 'sales_return');
                })
            )
            ->when($request->filled('product'), function ($query) use ($request) {
                $keyword = $request->input('product');
                $query->whereHas('product', function ($product) use ($keyword) {
                    $product->where('code', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%")
                        ->orWhere('barcode', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('serial'), fn ($query) => $query->where('serial_no', 'like', "%{$request->input('serial')}%"))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('shop::warehouse.serials.index', compact('serials', 'status'));
    }
}
