<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\WarehouseRequest;
use App\Modules\Shop\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        $warehouses = Warehouse::withCount('locations')->orderBy('code')->get();

        return view('shop::master.warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        return view('shop::master.warehouses.create');
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());

        return redirect()->route('shop.warehouses.index')->with('status', '仓库已创建');
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('shop::master.warehouses.edit', compact('warehouse'));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        return redirect()->route('shop.warehouses.index')->with('status', '仓库已更新');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->locations()->exists()) {
            return redirect()->route('shop.warehouses.index')
                ->with('error', '该仓库下存在库位，不能删除');
        }

        $warehouse->delete();

        return redirect()->route('shop.warehouses.index')->with('status', '仓库已删除');
    }
}
