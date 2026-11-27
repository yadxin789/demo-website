<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 系统（H5-02）：账号、角色权限矩阵、站内通知。
 *
 * 角色与权限按本产品 guard「hr」隔离（HC-11），只操作 hr.sqlite 里的 spatie 表。
 */
class SystemController extends Controller
{
    /** @var array<string, string> */
    private const ROLE_TEXT = [
        'admin' => '系统管理员',
        'hr_manager' => '人事经理',
        'hr_specialist' => '人事专员',
        'dept_manager' => '部门主管',
        'employee' => '普通员工',
    ];

    public function users(Request $request): View
    {
        $this->denyUnlessAdmin();

        $q = trim((string) $request->query('q', ''));

        $rows = User::query()
            ->with('roles:id,name')
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('employee_no', 'like', "%{$q}%");
            }))
            ->orderBy('id')
            ->get();

        return view('hr::system.users', [
            'rows' => $rows,
            'q' => $q,
            'roleText' => self::ROLE_TEXT,
            'roles' => array_keys(self::ROLE_TEXT),
        ]);
    }

    public function userCreate(): View
    {
        $this->denyUnlessAdmin();

        return view('hr::system.user-form', [
            'user' => new User(['status' => 'active', 'role_code' => 'employee']),
            'roleText' => self::ROLE_TEXT,
            'roles' => array_keys(self::ROLE_TEXT),
            'mode' => 'create',
        ]);
    }

    public function userStore(Request $request): RedirectResponse
    {
        $this->denyUnlessAdmin();
        $data = $this->validateAccount($request);

        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'] ?: null,
            'email' => $data['email'],
            'employee_no' => $data['employee_no'] ?: null,
            'role_code' => $data['role'],
            'password' => $data['password'],
            'status' => $data['status'],
        ]);
        $user->syncRoles([$data['role']]);

        return redirect()->route('hr.system.users.index')
            ->with('status', "已新增账号 {$user->name}（{$user->email}），角色：" . self::ROLE_TEXT[$data['role']]);
    }

    public function userEdit(User $user): View
    {
        $this->denyUnlessAdmin();

        return view('hr::system.user-form', [
            'user' => $user->load('roles:id,name'),
            'roleText' => self::ROLE_TEXT,
            'roles' => array_keys(self::ROLE_TEXT),
            'mode' => 'edit',
        ]);
    }

    public function userProfile(Request $request, User $user): RedirectResponse
    {
        $this->denyUnlessAdmin();
        $data = $this->validateAccount($request, $user);

        if ($user->id === (int) auth('hr')->id() && ($data['role'] !== 'admin' || $data['status'] !== 'active')) {
            return back()->with('error', '不能修改当前登录管理员的角色或停用它，否则会锁死自己。');
        }

        $payload = [
            'name' => $data['name'],
            'username' => $data['username'] ?: null,
            'email' => $data['email'],
            'employee_no' => $data['employee_no'] ?: null,
            'role_code' => $data['role'],
            'status' => $data['status'],
        ];
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->syncRoles([$data['role']]);

        return redirect()->route('hr.system.users.index')
            ->with('status', "账号 {$user->name} 已保存" . (empty($data['password']) ? '（密码未改动）' : '（密码已重置）'));
    }

    public function userDestroy(User $user): RedirectResponse
    {
        $this->denyUnlessAdmin();

        if ($user->id === (int) auth('hr')->id()) {
            return back()->with('error', '不能删除当前登录的账号。');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('hr.system.users.index')->with('status', "账号 {$name} 已删除。");
    }

    /** @return array<string, mixed> */
    private function validateAccount(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'username' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('hr.users', 'username')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:120', Rule::unique('hr.users', 'email')->ignore($user?->id)],
            'employee_no' => ['nullable', 'string', 'max:40', Rule::unique('hr.users', 'employee_no')->ignore($user?->id)],
            'role' => ['required', Rule::in(['admin', 'hr_manager', 'hr_specialist', 'dept_manager', 'employee'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'max:64'],
            'status' => ['required', 'in:active,disabled'],
        ], [], [
            'name' => '姓名', 'username' => '用户名', 'email' => '邮箱', 'employee_no' => '工号',
            'role' => '角色', 'password' => '密码', 'status' => '状态',
        ]);
    }

    public function userRole(Request $request, User $user): RedirectResponse
    {
        $this->denyUnlessAdmin();

        $data = $request->validate([
            'role' => ['required', 'in:admin,hr_manager,hr_specialist,dept_manager,employee'],
        ], [], ['role' => '角色']);

        if ($user->id === auth('hr')->id() && $data['role'] !== 'admin') {
            return back()->with('error', '不能把当前登录的自己降级，否则会锁死自己。');
        }

        $user->syncRoles([$data['role']]);
        $user->forceFill(['role_code' => $data['role']])->save();

        return redirect()->route('hr.system.users.index')
            ->with('status', "{$user->name} 的角色已改为「" . self::ROLE_TEXT[$data['role']] . '」。');
    }

    public function userToggle(User $user): RedirectResponse
    {
        $this->denyUnlessAdmin();

        if ($user->id === auth('hr')->id()) {
            return back()->with('error', '不能停用当前登录的账号。');
        }

        $next = $user->status === 'active' ? 'disabled' : 'active';
        $user->forceFill(['status' => $next])->save();

        return redirect()->route('hr.system.users.index')
            ->with('status', "{$user->name} 已" . ($next === 'active' ? '启用' : '停用') . '。');
    }

    public function roles(): View
    {
        $this->denyUnlessAdmin();

        $roles = Role::query()->orderBy('id')->get();
        $permissions = Permission::query()->orderBy('id')->get();

        $matrix = [];
        foreach ($roles as $role) {
            $matrix[$role->name] = $role->permissions->pluck('name')->all();
        }

        return view('hr::system.roles', [
            'roles' => $roles,
            'permissions' => $permissions,
            'matrix' => $matrix,
            'roleText' => self::ROLE_TEXT,
        ]);
    }

    public function rolePermissions(Request $request, string $role): RedirectResponse
    {
        $this->denyUnlessAdmin();

        $model = Role::query()->where('name', $role)->first();
        if ($model === null) {
            return back()->with('error', "角色 {$role} 不存在。");
        }

        $perms = (array) $request->input('permissions', []);
        $valid = Permission::query()->whereIn('name', $perms)->pluck('name')->all();

        $model->syncPermissions($valid);

        return redirect()->route('hr.system.roles.index')
            ->with('status', "角色「" . (self::ROLE_TEXT[$role] ?? $role) . '」权限已更新为 ' . count($valid) . ' 项。');
    }

    public function notifications(): View
    {
        $me = auth('hr')->id();

        $rows = DB::connection('hr')->table('notifications')
            ->where('notifiable_id', $me)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($n) {
                $payload = json_decode((string) $n->data, true) ?: [];

                return (object) [
                    'id' => $n->id,
                    'title' => $payload['title'] ?? '通知',
                    'body' => $payload['body'] ?? '',
                    'level' => $payload['level'] ?? 'info',
                    'from' => $payload['from'] ?? '系统',
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ];
            });

        return view('hr::system.notifications', [
            'rows' => $rows,
            'unread' => $rows->whereNull('read_at')->count(),
            'accounts' => User::query()->orderBy('id')->get(['id', 'name', 'role_code']),
            'isAdmin' => (bool) auth('hr')->user()?->hasRole('admin'),
        ]);
    }

    public function notifySend(Request $request): RedirectResponse
    {
        $this->denyUnlessAdmin();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:200'],
            'level' => ['required', 'in:info,success,warning'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['integer'],
        ], [], [
            'title' => '标题',
            'body' => '内容',
            'level' => '类型',
            'targets' => '接收人',
        ]);

        $me = auth('hr')->user();
        $ids = User::query()->whereIn('id', $data['targets'])->pluck('id')->all();
        $now = now();

        $insert = [];
        foreach ($ids as $id) {
            $insert[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Modules\\Hr\\Notifications\\Announcement',
                'notifiable_type' => User::class,
                'notifiable_id' => $id,
                'data' => json_encode([
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'level' => $data['level'],
                    'from' => $me?->name ?? '系统',
                ], JSON_UNESCAPED_UNICODE),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection('hr')->table('notifications')->insert($insert);

        return redirect()->route('hr.system.notifications')
            ->with('status', "通知已发给 {$data['title']} ✓ 共 " . count($insert) . ' 人。');
    }

    public function notifyRead(Request $request): RedirectResponse
    {
        $me = auth('hr')->id();

        DB::connection('hr')->table('notifications')
            ->where('notifiable_id', $me)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return redirect()->route('hr.system.notifications')->with('status', '已全部标记为已读。');
    }

    /**
     * 账号 / 角色权限 / 发通知只给 admin；403 判定放方法首行，避免 route:list 抛错。
     */
    private function denyUnlessAdmin(): void
    {
        $me = auth('hr')->user();

        if ($me === null || ! $me->hasRole('admin')) {
            abort(403, '该页面仅系统管理员可访问。');
        }
    }
}


