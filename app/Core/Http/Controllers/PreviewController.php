<?php

declare(strict_types=1);

namespace App\Core\Http\Controllers;

use App\Core\Support\Product;

/**
 * 产品预览页：免登录可看介绍，内容全部来自 config/products.php。
 *
 * HC-14：本页面不得查询任何产品库 —— 客户还没登录、库可能是空的。
 */
class PreviewController
{
    public function show(string $product)
    {
        $item = Product::find($product);

        if ($item === null || ($item['enabled'] ?? false) !== true) {
            abort(404);
        }

        return view('portal::preview', ['p' => $item]);
    }
}