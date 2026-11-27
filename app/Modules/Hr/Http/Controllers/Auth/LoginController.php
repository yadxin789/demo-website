<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers\Auth;

use App\Modules\Hr\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * HR 独立登录（HC-11：不进门户统一登录，不做 SSO）。
 *
 * 账号可以是 email 或姓名，密码统一 123456（只在 Seeder 里写，HC-13）。
 */
class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('hr')->check()) {
            return redirect()->route('hr.dashboard');
        }

        return view('hr::auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ], [], [
            'account' => '账号',
            'password' => '密码',
        ]);

        // S9：邮箱或用户名均可登录（HC-42）。原实现是"邮箱或姓名"。
        $field = filter_var($data['account'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $ok = Auth::guard('hr')->attempt([
            $field => $data['account'],
            'password' => $data['password'],
            'status' => 'active',
        ], $request->boolean('remember'));

        if (! $ok) {
            return back()
                ->withInput($request->only('account'))
                ->withErrors(['account' => '账号或密码不正确']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('hr.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('hr')->logout();

        // 只清本 guard 的登录态，不销毁整个会话（同一浏览器里其它产品可能还登着）
        $request->session()->forget('login_hr_'.sha1('App\\Modules\\Hr\\Models\\User'));

        return redirect()->route('hr.login')->with('status', '已退出登录');
    }
}
