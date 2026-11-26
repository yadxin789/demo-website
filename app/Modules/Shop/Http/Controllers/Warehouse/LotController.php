<?php

namespace App\Modules\Shop\Http\Controllers\Warehouse;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Lot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * 批次效期预警：列出在库批次，标识过期 / 临期 / 正常，按效期升序。
 */
class LotController extends Controller
{
    public function index(Request $request): View
    {
        $thresholdDays = (int) config('shop.inventory.expiry_warning_days', 30);
        $today = Carbon::today();
        $deadline = $today->copy()->addDays($thresholdDays);
        $status = $request->query('status', '');

        $lots = Lot::query()
            ->with(['product', 'supplier'])
            ->withSum('stockLevels as on_hand', 'qty')
            ->when($status === 'expired', fn ($query) => $query->whereDate('expiry_date', '<', $today))
            ->when($status === 'expiring', fn ($query) => $query
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $deadline))
            ->when($status === 'normal', fn ($query) => $query
                ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>', $deadline)))
            ->orderBy('expiry_date')
            ->paginate(20)
            ->withQueryString();

        return view('shop::warehouse.lots.index', compact('lots', 'thresholdDays', 'status'));
    }
}
