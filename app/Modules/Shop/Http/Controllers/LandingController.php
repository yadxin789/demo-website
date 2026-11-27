<?php

declare(strict_types=1);

namespace App\Modules\Shop\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Shop 对外首页（S8-08）。
 *
 * 基线是 E:\erp-shop 的 landing（POS 收银 + 进销存能力说明），
 * 这里按五产品统一首页骨架重排文案，已登录直接进工作台。
 */
class LandingController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (auth('shop')->check()) {
            return redirect()->route('shop.dashboard');
        }

        return view('shop::landing');
    }
}
