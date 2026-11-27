<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\System;

use App\Modules\Mes\Database\Seeders\RoleSeeder;
use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 系统 · 账号（M6-04）。
 *
 * 演示站只提供「查看 + 改角色 + 启停」三件事，不在页面上建号、不重置密码：
 * 演示密码只允许出现在 Seeder（门禁 G-10），页面上任何硬编码都会被打回。
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin();
        $status = $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with('roles:id,name')
            ->when($status !== '', fn ($b) => $b->where('status', $status))
            ->when($q !== '', fn ($b) => $b->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('employee_no', 'like', "%{$q}%");
            }))
            ->orderBy('id')
            ->get();

        return view('mes::system.users.index', [
            'users' => $users,
            'roles' => RoleSeeder::ROLES,
            'status' => $status,
            'q' => $q,
            'stat' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('status', 'active')->count(),
                'disabled' => User::query()->where('status', '!=', 'active')->count(),
            ],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(RoleSeeder::ROLES))],
            'status' => ['required', 'in:active,disabled'],
        ], [], ['role' => '角色', 'status' => '状态']);

        if ($user->id === (int) auth('mes')->id() && $data['status'] !== 'active') {
            return back()->withErrors(['status' => '不能停用当前登录的管理员账号。']);
        }

        $user->syncRoles([$data['role']]);
        $user->update(['status' => $data['status']]);

        return redirect()->route('mes.system.users.index')
            ->with('status', "账号 {$user->name} 已更新：" . RoleSeeder::ROLES[$data['role']] . ' / ' . ($data['status'] === 'active' ? '启用' : '停用'));
    }

    /**
     * 权限判定放在方法里而不是构造函数：
     * route:list / 容器预解析时没有登录态，构造里 abort 会把命令行也拦掉。
     */
    private function ensureAdmin(): void
    {
        if (! auth('mes')->user()?->hasRole('admin')) {
            abort(403, '系统页只对系统管理员开放。');
        }
    }
}
