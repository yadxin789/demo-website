<?php

declare(strict_types=1);

namespace App\Modules\Pm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * PM 对外首页（S8-06）。
 *
 * 区块顺序取自参考项目 pm-server-openEuler 的 welcome 首页（hero → 数字条 → 能力 → 适用人群），
 * 但演示站不查任何产品库，数字用静态口径（HC-41）。
 */
class LandingController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (auth('pm')->check()) {
            return redirect()->route('pm.dashboard');
        }

        return view('pm::landing');
    }
}
