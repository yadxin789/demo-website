<?php

declare(strict_types=1);

namespace App\Modules\Erp\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * ERP 对外首页（S8-07）。
 *
 * 参考项目 E:\ddwl-erp\erp 的 `/` 是登录后的工作台（home），没有免登录门面页，
 * 因此按 v4 规划 12.3 的规则：用统一的五产品首页骨架承载，文本改为电子制造 ERP。
 * 已登录访问直接进工作台，避免多一次点击。
 */
class LandingController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (auth('erp')->check()) {
            return redirect()->route('erp.home');
        }

        return view('erp::landing');
    }
}
