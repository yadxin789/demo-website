<?php

namespace App\Modules\Shop\Http\Controllers\Warehouse;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\Scrap;
use App\Modules\Shop\Models\Warehouse;
use App\Modules\Shop\Services\Warehouse\ScrapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScrapController extends Controller
{
    public function __construct(protected ScrapService $scraps)
    {
    }

    public function index(): View
    {
        $scraps = Scrap::with(['warehouse', 'operator'])
            ->withCount('lines')
            ->orderByDesc('id')
            ->paginate(20);

        return view('shop::warehouse.scraps.index', compact('scraps'));
    }

    public function create(): View
    {
        $warehouses = Warehouse::orderBy('code')->get();
        $locations = Location::orderBy('code')->get();
        $products = Product::where('is_active', true)->orderBy('code')->get();

        return view('shop::warehouse.scraps.create', compact('warehouses', 'locations', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'scrap_date' => ['nullable', 'date'],
            'loss_type' => ['nullable', 'in:expense,other'],
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.location_id' => ['nullable', 'exists:locations,id'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:50'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $scrap = $this->scraps->createScrap(
                array_merge($validated, ['operator_id' => auth()->id()]),
                $validated['lines']
            );
        } catch (InventoryException $exception) {
            return redirect()->route('shop.scraps.create')->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.scraps.show', ['scrap' => $scrap->id])
            ->with('status', "报废单 {$scrap->code} 已创建草稿，请核对后过账");
    }

    public function show(Scrap $scrap): View
    {
        $scrap->load(['lines.product', 'lines.location', 'warehouse', 'operator']);

        return view('shop::warehouse.scraps.show', compact('scrap'));
    }

    public function post(Scrap $scrap): RedirectResponse
    {
        try {
            $this->scraps->postScrap($scrap);
        } catch (InventoryException $exception) {
            return redirect()->route('shop.scraps.show', ['scrap' => $scrap->id])
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.scraps.show', ['scrap' => $scrap->id])
            ->with('status', "报废单 {$scrap->code} 已过账，库存已扣减并结转损失");
    }
}
