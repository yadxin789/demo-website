<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\LocationRequest;
use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(): View
    {
        $locations = Location::with(['warehouse', 'parent'])->orderBy('code')->get();
        $warehouses = Warehouse::active()->orderBy('code')->get();

        return view('shop::master.locations.index', compact('locations', 'warehouses'));
    }

    public function create(): View
    {
        $warehouses = Warehouse::active()->orderBy('code')->get();
        $parents = Location::orderBy('code')->get();

        return view('shop::master.locations.create', compact('warehouses', 'parents'));
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['barcode'] = $this->resolveBarcode($data);

        Location::create($data);

        return redirect()->route('shop.locations.index')->with('status', '库位已创建');
    }

    /**
     * 库位标签批量打印：CODE128 + 库位二维码。
     */
    public function labels(Request $request): View
    {
        $selected = array_filter((array) $request->input('selected', []));
        $locations = Location::with('warehouse')->where('is_active', true)
            ->orderBy('warehouse_id')->orderBy('code')->get();
        $labelLocations = $locations->whereIn('id', $selected)->values();

        return view('shop::master.locations.labels', compact('locations', 'selected', 'labelLocations'));
    }

    public function edit(Location $location): View
    {
        $warehouses = Warehouse::active()->orderBy('code')->get();
        $parents = Location::where('id', '!=', $location->id)->orderBy('code')->get();

        return view('shop::master.locations.edit', compact('location', 'warehouses', 'parents'));
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $data = $request->validated();
        $data['barcode'] = $this->resolveBarcode($data, $location);

        $location->update($data);

        return redirect()->route('shop.locations.index')->with('status', '库位已更新');
    }

    public function destroy(Location $location): RedirectResponse
    {
        if ($location->children()->exists()) {
            return redirect()->route('shop.locations.index')
                ->with('error', '该库位下存在子库位，不能删除');
        }

        $location->delete();

        return redirect()->route('shop.locations.index')->with('status', '库位已删除');
    }

    /**
     * 生成/保留库位条码：为空时按仓库编码 + 库位编码生成，冲突时追加序号。
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveBarcode(array $data, ?Location $location = null): string
    {
        $barcode = trim((string) ($data['barcode'] ?? ''));
        if ($barcode !== '') {
            return $barcode;
        }

        if ($location?->barcode) {
            return (string) $location->barcode;
        }

        $warehouse = Warehouse::find($data['warehouse_id']);
        $base = 'LOC-' . ($warehouse?->code ?? $data['warehouse_id']) . '-' . $data['code'];
        $base = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-');
        $base = mb_substr($base !== '' ? $base : 'LOC-' . $data['warehouse_id'], 0, 56);

        $candidate = $base;
        $suffix = 1;
        while (Location::where('barcode', $candidate)->when($location, fn ($q) => $q->where('id', '!=', $location->id))->exists()) {
            $suffix++;
            $candidate = $base . '-' . $suffix;
        }

        return $candidate;
    }
}
