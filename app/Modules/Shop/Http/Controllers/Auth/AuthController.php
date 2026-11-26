<?php

namespace App\Modules\Shop\Http\Controllers\Auth;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Auth\ChangePasswordRequest;
use App\Modules\Shop\Http\Requests\Auth\LoginRequest;
use App\Modules\Shop\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('shop/');
        }

        return view('shop::auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $login = trim((string) $request->input('username', ''));
        // 用户名或邮箱均可登录：形如邮箱时按 email 查，其余按 username 查
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field => $login,
            'password' => (string) $request->input('password', ''),
        ];

        if (! Auth::attempt($credentials, $remember = false)) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => '用户名/邮箱或密码错误']);
        }

        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => '该账号已停用，请联系管理员']);
        }

        $request->session()->regenerate();

        UserSession::create([
            'user_id' => $user->id,
            'login_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_token' => $request->session()->getId(),
        ]);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->intended('/')->with('status', '登录成功');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            UserSession::where('user_id', $user->id)
                ->whereNull('logout_at')
                ->update(['logout_at' => now()]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shop.login')->with('status', '已安全退出');
    }

    public function showChangePassword(): View
    {
        return view('shop::auth.password');
    }

    public function changePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => '当前密码不正确']);
        }

        $user->forceFill([
            'password' => $request->password,
            'remember_token' => Str::random(10),
        ])->save();

        // 登出其他设备会话，仅保留当前会话
        Auth::logoutOtherDevices($request->current_password);

        return back()->with('status', '密码已修改，其他设备已退出登录');
    }
}
