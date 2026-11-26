<?php

namespace App\Modules\Shop\Http\Controllers\Warehouse;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 库存流水查询：流水为不可变记录，页面仅展示与过滤，不提供编辑/删除。
 */
class MovementController extends Controller
{
    public function index(Request $request): View
    {
        $movements = StockMovement::query()
            ->with(['product', 'location', 'operator'])
            ->when($request->filled('product'), function ($query) use ($request) {
                $keyword = $request->input('product');
                $query->whereHas('product', function ($product) use ($keyword) {
                    $product->where('code', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%")
                        ->orWhere('barcode', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('location_id'), fn ($query) => $query->where('location_id', $request->input('location_id')))
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->input('direction')))
            ->when($request->filled('source_code'), fn ($query) => $query->where('source_code', 'like', "%{$request->input('source_code')}%"))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->input('date_to')))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $locations = Location::orderBy('code')->get();

        $directions = [
            StockMovement::DIRECTION_IN => '入库',
            StockMovement::DIRECTION_OUT => '出库',
            StockMovement::DIRECTION_TRANSFER => '调拨',
            StockMovement::DIRECTION_ADJUST => '调整',
        ];

        return view('shop::warehouse.movements.index', compact('movements', 'locations', 'directions'));
    }
}
