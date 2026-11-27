<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\System;

use App\Modules\Mes\Database\Seeders\RoleSeeder;
use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\Department;
use App\Modules\Mes\Models\Position;
use App\Modules\Mes\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 系统 · 账号（M6-04 / S12-M05）。
 *
 * 演示站支持「查看 + 新建 + 编辑 + 启停 + 改角色 + 删除」六件事。
 * 密码只由操作者在表单里填入，页面与代码中不出现任何硬编码口令（门禁 G-10）。
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin();
        $status = $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with(['roles:id,name', 'department:id,name', 'position:id,name'])
            ->when($status !== '', fn ($b) => $b->where('status', $status))
            ->when($q !== '', fn ($b) => $b->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
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

    public function create(): View
    {
        $this->ensureAdmin();

        return view('mes::system.users.form', [
            'user' => new User(['status' => 'active']),
            'roles' => RoleSeeder::ROLES,
            'departments' => Department::query()->active()->orderBy('sort')->orderBy('id')->get(),
            'positions' => Position::query()->active()->orderBy('id')->get(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'username' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('mes.users', 'username')],
            'email' => ['required', 'email', 'max:120', Rule::unique('mes.users', 'email')],
            'employee_no' => ['nullable', 'string', 'max:40', Rule::unique('mes.users', 'employee_no')],
            'department_id' => ['nullable', 'integer', Rule::exists('mes.departments', 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists('mes.positions', 'id')],
            'role' => ['required', Rule::in(array_keys(RoleSeeder::ROLES))],
            'password' => ['required', 'string', 'min:6', 'max:64'],
            'status' => ['required', 'in:active,disabled'],
        ], [], [
            'name' => '姓名', 'username' => '用户名', 'email' => '邮箱', 'employee_no' => '工号',
            'department_id' => '部门', 'position_id' => '岗位', 'role' => '角色',
            'password' => '密码', 'status' => '状态',
        ]);

        $department = $data['department_id'] ? Department::query()->find($data['department_id']) : null;

        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'] ?: null,
            'email' => $data['email'],
            'employee_no' => $data['employee_no'] ?: null,
            'department_id' => $data['department_id'] ?? null,
            'position_id' => $data['position_id'] ?? null,
            'team' => $department?->name,
            'password' => $data['password'],
            'status' => $data['status'],
        ]);
        $user->syncRoles([$data['role']]);

        return redirect()->route('mes.system.users.index')
            ->with('status', "已新增账号 {$user->name}（{$user->email}），角色：" . RoleSeeder::ROLES[$data['role']]);
    }

    public function edit(User $user): View
    {
        $this->ensureAdmin();

        return view('mes::system.users.form', [
            'user' => $user->load('roles:id,name'),
            'roles' => RoleSeeder::ROLES,
            'departments' => Department::query()->active()->orderBy('sort')->orderBy('id')->get(),
            'positions' => Position::query()->active()->orderBy('id')->get(),
            'mode' => 'edit',
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

    /** 编辑页提交：基本信息 + 角色 + 状态（密码留空则不修改） */
    public function profile(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'username' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('mes.users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:120', Rule::unique('mes.users', 'email')->ignore($user->id)],
            'employee_no' => ['nullable', 'string', 'max:40', Rule::unique('mes.users', 'employee_no')->ignore($user->id)],
            'department_id' => ['nullable', 'integer', Rule::exists('mes.departments', 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists('mes.positions', 'id')],
            'role' => ['required', Rule::in(array_keys(RoleSeeder::ROLES))],
            'password' => ['nullable', 'string', 'min:6', 'max:64'],
            'status' => ['required', 'in:active,disabled'],
        ], [], [
            'name' => '姓名', 'username' => '用户名', 'email' => '邮箱', 'employee_no' => '工号',
            'department_id' => '部门', 'position_id' => '岗位', 'role' => '角色',
            'password' => '密码', 'status' => '状态',
        ]);

        if ($user->id === (int) auth('mes')->id() && $data['status'] !== 'active') {
            return back()->withErrors(['status' => '不能停用当前登录的管理员账号。']);
        }

        $department = $data['department_id'] ? Department::query()->find($data['department_id']) : null;
        $payload = [
            'name' => $data['name'],
            'username' => $data['username'] ?: null,
            'email' => $data['email'],
            'employee_no' => $data['employee_no'] ?: null,
            'department_id' => $data['department_id'] ?? null,
            'position_id' => $data['position_id'] ?? null,
            'team' => $department?->name,
            'status' => $data['status'],
        ];
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->syncRoles([$data['role']]);

        return redirect()->route('mes.system.users.index')
            ->with('status', "账号 {$user->name} 已保存" . (empty($data['password']) ? '（密码未改动）' : '（密码已重置）'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureAdmin();

        if ($user->id === (int) auth('mes')->id()) {
            return back()->withErrors(['user' => '不能删除当前登录的账号。']);
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('mes.system.users.index')->with('status', "账号 {$name} 已删除。");
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
