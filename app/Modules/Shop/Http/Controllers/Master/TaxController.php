<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\TaxRequest;
use App\Modules\Shop\Models\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaxController extends Controller
{
    public function index(): View
    {
        $taxes = Tax::orderBy('code')->get();

        return view('shop::master.taxes.index', compact('taxes'));
    }

    public function create(): View
    {
        return view('shop::master.taxes.create');
    }

    public function store(TaxRequest $request): RedirectResponse
    {
        Tax::create($request->validated());

        return redirect()->route('shop.taxes.index')->with('status', '税率已创建');
    }

    public function edit(Tax $tax): View
    {
        return view('shop::master.taxes.edit', compact('tax'));
    }

    public function update(TaxRequest $request, Tax $tax): RedirectResponse
    {
        $tax->update($request->validated());

        return redirect()->route('shop.taxes.index')->with('status', '税率已更新');
    }

    public function destroy(Tax $tax): RedirectResponse
    {
        if ($tax->products()->exists()) {
            return redirect()->route('shop.taxes.index')
                ->with('error', '该税率已被商品引用，不能删除');
        }

        $tax->delete();

        return redirect()->route('shop.taxes.index')->with('status', '税率已删除');
    }
}
