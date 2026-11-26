<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\PriceListItemRequest;
use App\Modules\Shop\Models\PriceList;
use App\Modules\Shop\Models\PriceListItem;
use App\Modules\Shop\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PriceListItemController extends Controller
{
    public function edit(PriceList $price_list, PriceListItem $item): View
    {
        $products = Product::active()->orderBy('code')->get();

        return view('shop::master.price-lists.item-edit', compact('price_list', 'item', 'products'));
    }

    public function store(PriceListItemRequest $request, PriceList $price_list): RedirectResponse
    {
        $price_list->items()->create($request->validated());

        return redirect()->route('shop.price-lists.edit', $price_list)->with('status', '阶梯价格已添加');
    }

    public function update(PriceListItemRequest $request, PriceList $price_list, PriceListItem $item): RedirectResponse
    {
        $item->update($request->validated());

        return redirect()->route('shop.price-lists.edit', $price_list)->with('status', '阶梯价格已更新');
    }

    public function destroy(PriceList $price_list, PriceListItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('shop.price-lists.edit', $price_list)->with('status', '阶梯价格已删除');
    }
}
