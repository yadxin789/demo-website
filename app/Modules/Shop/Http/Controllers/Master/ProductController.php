<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Exceptions\ImageException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\ProductRequest;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductCategory;
use App\Modules\Shop\Models\Tax;
use App\Modules\Shop\Models\Unit;
use App\Modules\Shop\Services\Master\ProductImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly ProductImageService $images)
    {
    }

    public function index(): View
    {
        $products = Product::with(['category', 'unit', 'tax'])->orderBy('code')->get();

        return view('shop::master.products.index', compact('products'));
    }

    public function create(): View
    {
        [$categories, $units, $taxes] = $this->lookups();

        return view('shop::master.products.create', ['product' => null] + compact('categories', 'units', 'taxes'));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($this->payload($request));

        if ($request->hasFile('image')) {
            try {
                $this->images->store($product, $request->file('image'));
            } catch (ImageException $e) {
                return redirect()->route('shop.products.edit', $product)
                    ->withErrors(['image' => $e->getMessage()])
                    ->with('status', '商品已创建，但图片保存失败');
            }
        }

        return redirect()->route('shop.products.index')->with('status', '商品已创建');
    }

    public function edit(Product $product): View
    {
        [$categories, $units, $taxes] = $this->lookups();

        return view('shop::master.products.edit', compact('product', 'categories', 'units', 'taxes'));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($this->payload($request));

        if ($request->hasFile('image')) {
            try {
                $this->images->store($product, $request->file('image'));
            } catch (ImageException $e) {
                return redirect()->route('shop.products.edit', $product)
                    ->withErrors(['image' => $e->getMessage()])
                    ->with('status', '商品已更新，但图片保存失败');
            }
        } elseif ($request->boolean('remove_image')) {
            $this->images->remove($product);
        }

        return redirect()->route('shop.products.index')->with('status', '商品已更新');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // 关联库存单据建成后回补：存在库存/出入库单据时禁止删除，本期先做软拦截提示
        $this->images->remove($product);
        $product->delete();

        return redirect()->route('shop.products.index')->with('status', '商品已删除');
    }

    /**
     * 去掉文件字段，图片由 ProductImageService 单独落盘。
     */
    private function payload(ProductRequest $request): array
    {
        return Arr::except($request->validated(), ['image', 'remove_image']);
    }

    /**
     * @return array{0: \Illuminate\Database\Eloquent\Collection, 1: \Illuminate\Database\Eloquent\Collection, 2: \Illuminate\Database\Eloquent\Collection}
     */
    private function lookups(): array
    {
        return [
            ProductCategory::orderBy('name')->get(),
            Unit::orderBy('code')->get(),
            Tax::active()->orderBy('code')->get(),
        ];
    }
}
