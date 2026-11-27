<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * MES 对外首页（S8-09）。
 *
 * 参考项目无 MES，按 v4 规划 12.3 复制 PM 首页结构，文案改为智能制造 MES。
 */
class LandingController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (auth('mes')->check()) {
            return redirect()->route('mes.dashboard');
        }

        return view('mes::landing');
    }
}
