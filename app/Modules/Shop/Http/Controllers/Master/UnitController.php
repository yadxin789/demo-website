<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\UnitRequest;
use App\Modules\Shop\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        $units = Unit::orderBy('code')->get();

        return view('shop::master.units.index', compact('units'));
    }

    public function create(): View
    {
        return view('shop::master.units.create');
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        Unit::create($request->validated());

        return redirect()->route('shop.units.index')->with('status', '计量单位已创建');
    }

    public function edit(Unit $unit): View
    {
        return view('shop::master.units.edit', compact('unit'));
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validated());

        return redirect()->route('shop.units.index')->with('status', '计量单位已更新');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->products()->exists()) {
            return redirect()->route('shop.units.index')
                ->with('error', '该单位已被商品引用，不能删除');
        }

        $unit->delete();

        return redirect()->route('shop.units.index')->with('status', '计量单位已删除');
    }
}
