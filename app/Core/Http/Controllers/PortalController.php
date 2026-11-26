<?php

declare(strict_types=1);

namespace App\Core\Http\Controllers;

use App\Core\Support\Product;

/**
 * 门户首页：只列 enabled=true 的产品，不碰任何产品库（HC-14）。
 */
class PortalController
{
    public function home()
    {
        return view('portal::home', [
            'products' => Product::enabled(),
        ]);
    }
}