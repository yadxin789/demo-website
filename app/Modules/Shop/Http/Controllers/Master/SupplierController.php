<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\SupplierRequest;
use App\Modules\Shop\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::orderBy('code')->get();

        return view('shop::master.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('shop::master.suppliers.create');
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return redirect()->route('shop.suppliers.index')->with('status', '供应商已创建');
    }

    public function edit(Supplier $supplier): View
    {
        return view('shop::master.suppliers.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('shop.suppliers.index')->with('status', '供应商已更新');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()->route('shop.suppliers.index')->with('status', '供应商已删除');
    }
}
