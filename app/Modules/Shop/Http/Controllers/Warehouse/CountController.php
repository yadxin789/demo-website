<?php

namespace App\Modules\Shop\Http\Controllers\Warehouse;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Warehouse\CountRequest;
use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\StockCount;
use App\Modules\Shop\Models\Warehouse;
use App\Modules\Shop\Services\Inventory\CountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountController extends Controller
{
    public function __construct(protected CountService $counts)
    {
    }

    public function index(): View
    {
        $counts = StockCount::with(['warehouse', 'location', 'operator'])
            ->withCount('lines')
            ->orderByDesc('id')
            ->paginate(20);

        return view('shop::warehouse.counts.index', compact('counts'));
    }

    public function create(): View
    {
        $warehouses = Warehouse::orderBy('code')->get();
        $locations = Location::orderBy('code')->get();

        return view('shop::warehouse.counts.create', compact('warehouses', 'locations'));
    }

    public function store(CountRequest $request): RedirectResponse
    {
        $count = $this->counts->createCount(array_merge($request->validated(), [
            'operator_id' => auth()->id(),
        ]));

        return redirect()->route('shop.counts.show', $count)->with('status', "盘点单 {$count->code} 已创建，请录入实盘数");
    }

    public function show(StockCount $count): View
    {
        $count->load(['lines.product', 'lines.location', 'warehouse', 'location', 'operator']);
        $pending = $this->counts->pendingCount($count);
        $totalVariance = $this->counts->totalVarianceValue($count);

        return view('shop::warehouse.counts.show', compact('count', 'pending', 'totalVariance'));
    }

    public function enter(StockCount $count): RedirectResponse
    {
        $lines = collect(request()->input('counted', []))
            ->map(fn ($value, $lineId) => ['line_id' => (int) $lineId, 'counted_qty' => $value])
            ->values()
            ->all();

        try {
            $this->counts->enterCounted($count, $lines);
        } catch (InventoryException $exception) {
            return redirect()->route('shop.counts.show', $count)->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.counts.show', $count)->with('status', '实盘数已保存，盈亏已重算');
    }

    public function scan(Request $request, StockCount $count): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'qty' => ['nullable', 'numeric', 'min:0.0001'],
        ]);

        try {
            $line = $this->counts->scanCounted($count, $data['code'], (float) ($data['qty'] ?? 1));
        } catch (\InvalidArgumentException|InventoryException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        $count->load('lines.product');
        $pending = $this->counts->pendingCount($count);

        return response()->json([
            'ok' => true,
            'line' => $line,
            'pending' => $pending,
            'total_variance' => $this->counts->totalVarianceValue($count),
        ]);
    }

    public function post(StockCount $count): RedirectResponse
    {
        try {
            $this->counts->postCount($count);
        } catch (InventoryException $exception) {
            return redirect()->route('shop.counts.show', $count)->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.counts.show', $count)->with('status', '盘点单已过账，库存已按实盘调整');
    }
}
