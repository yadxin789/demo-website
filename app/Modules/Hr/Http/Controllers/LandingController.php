<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * HR 对外首页（S8-10）。
 *
 * 参考项目无 HR，按 v4 规划 12.3 复制 PM 首页结构，文案改为人事管理 HR。
 */
class LandingController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (auth('hr')->check()) {
            return redirect()->route('hr.dashboard');
        }

        return view('hr::landing');
    }
}
