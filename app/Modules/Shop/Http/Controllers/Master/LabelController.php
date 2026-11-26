<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabelController extends Controller
{
    public function index(Request $request): View
    {
        $selected = array_filter((array) $request->input('selected', []));

        $products = Product::active()
            ->when($request->input('q'), function ($query, $q) {
                $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%"));
            })
            ->orderBy('code')
            ->get();

        $labelProducts = $products->whereIn('id', $selected)->values();

        return view('shop::master.labels.index', compact('products', 'selected', 'labelProducts'));
    }
}
