<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\System;

use App\Modules\Mes\Database\Seeders\PermissionSeeder;
use App\Modules\Mes\Database\Seeders\RoleSeeder;
use App\Modules\Mes\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 系统 · 角色权限矩阵（M6-04 / M6-05）。
 *
 * 五个角色 × 14 条权限一屏看完，可勾选保存；admin 固定全量、不开放编辑，
 * 避免演示时把自己锁在门外。保存后清 spatie 缓存，权限立即生效。
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $this->ensureAdmin();
        $roles = Role::query()
            ->where('guard_name', 'mes')
            ->with('permissions:id,name')
            ->get()
            ->keyBy('name');

        $order = array_keys(RoleSeeder::ROLES);
        $ordered = collect($order)->map(fn ($name) => $roles->get($name))->filter()->values();

        return view('mes::system.roles.index', [
            'roles' => $ordered,
            'roleLabels' => RoleSeeder::ROLES,
            'permissions' => PermissionSeeder::PERMISSIONS,
            'holders' => $this->holderCount(),
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $this->ensureAdmin();
        if (! isset(RoleSeeder::ROLES[$role])) {
            abort(404);
        }

        if ($role === 'admin') {
            return redirect()->route('mes.system.roles.index')
                ->withErrors(['permissions' => '系统管理员默认拥有全部权限，不允许收窄。']);
        }

        $valid = array_keys(PermissionSeeder::PERMISSIONS);
        $perms = collect($request->input('permissions', []))
            ->filter(fn ($p) => in_array($p, $valid, true))
            ->values()
            ->all();

        // 权限记录必须存在（首次可能尚未 seed），这里兜底创建
        foreach ($perms as $name) {
            Permission::findOrCreate($name, 'mes');
        }

        Role::findByName($role, 'mes')->syncPermissions($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('mes.system.roles.index')
            ->with('status', RoleSeeder::ROLES[$role] . ' 权限已更新：' . count($perms) . ' 条');
    }

    /** 每个角色当前有几个账号在用，矩阵上顺带展示，避免盲改 */
    private function holderCount(): array
    {
        return DB::connection('mes')->table('model_has_roles')
            ->selectRaw('role_id, count(*) as n')
            ->groupBy('role_id')
            ->pluck('n', 'role_id')
            ->all();
    }

    private function ensureAdmin(): void
    {
        if (! auth('mes')->user()?->hasRole('admin')) {
            abort(403, '系统页只对系统管理员开放。');
        }
    }
}
