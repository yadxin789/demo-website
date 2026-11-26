<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Services\Master\ProductImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 商品图片读取与删除。
 *
 * 图片不通过 public/storage 软链接暴露，而是由本控制器从本地磁盘读流输出，
 * 保证离线部署时无需 `storage:link`，也不会产生外链请求。
 */
class ProductImageController extends Controller
{
    private const VARIANTS = ['thumb', 'full'];

    public function __construct(private readonly ProductImageService $images)
    {
    }

    public function show(Product $product, string $variant = 'thumb'): BinaryFileResponse
    {
        abort_unless(in_array($variant, self::VARIANTS, true), 404);

        $path = $this->images->path($product, $variant);
        if ($path === null) {
            abort(404, '商品图片不存在');
        }

        return response()->file(Storage::disk(ProductImageService::DISK)->path($path), [
            'Content-Type' => $this->images->mime($product, $variant),
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->images->remove($product);

        return redirect()->route('shop.products.edit', $product)->with('status', '商品图片已移除');
    }
}
