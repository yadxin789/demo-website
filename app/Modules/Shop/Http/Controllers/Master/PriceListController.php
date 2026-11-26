<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\PriceListRequest;
use App\Modules\Shop\Models\PriceList;
use App\Modules\Shop\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PriceListController extends Controller
{
    public function index(): View
    {
        $lists = PriceList::withCount('items')->orderBy('id')->get();

        return view('shop::master.price-lists.index', compact('lists'));
    }

    public function create(): View
    {
        return view('shop::master.price-lists.create');
    }

    public function store(PriceListRequest $request): RedirectResponse
    {
        PriceList::create($request->validated());

        return redirect()->route('shop.price-lists.index')->with('status', '价格表已创建');
    }

    public function edit(PriceList $price_list): View
    {
        $price_list->load(['items.product']);
        $products = Product::active()->orderBy('code')->get();

        return view('shop::master.price-lists.edit', compact('price_list', 'products'));
    }

    public function update(PriceListRequest $request, PriceList $price_list): RedirectResponse
    {
        $price_list->update($request->validated());

        return redirect()->route('shop.price-lists.index')->with('status', '价格表已更新');
    }

    public function destroy(PriceList $price_list): RedirectResponse
    {
        $price_list->delete();

        return redirect()->route('shop.price-lists.index')->with('status', '价格表已删除');
    }
}
