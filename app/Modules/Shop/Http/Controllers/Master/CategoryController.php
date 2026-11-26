<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\CategoryRequest;
use App\Modules\Shop\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = ProductCategory::with('parent')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return view('shop::master.categories.index', compact('categories'));
    }

    public function create(): View
    {
        $parents = ProductCategory::active()->orderBy('name')->get();

        return view('shop::master.categories.create', compact('parents'));
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        ProductCategory::create($request->validated());

        return redirect()->route('shop.categories.index')->with('status', '类别已创建');
    }

    public function edit(ProductCategory $category): View
    {
        $parents = ProductCategory::active()
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get();

        return view('shop::master.categories.edit', compact('category', 'parents'));
    }

    public function update(CategoryRequest $request, ProductCategory $category): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $category->id) {
            return back()->withErrors(['parent_id' => '上级类别不能选择自身']);
        }

        $category->update($data);

        return redirect()->route('shop.categories.index')->with('status', '类别已更新');
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->withErrors(['destroy' => '该类别存在子类别，无法删除']);
        }

        if ($category->products()->exists()) {
            return back()->withErrors(['destroy' => '该类别下存在商品，无法删除']);
        }

        $category->delete();

        return redirect()->route('shop.categories.index')->with('status', '类别已删除');
    }
}
