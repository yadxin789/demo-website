<?php

declare(strict_types=1);

namespace App\Modules\Erp\Database\Seeders;

use App\Modules\Erp\Models\Organization\Position;
use App\Modules\Erp\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * 按岗位补齐账号角色（Erp）。
 *
 * 参考项目的侧边栏由"N 个岗位 → N 套菜单"驱动：账号只有绑定了岗位角色才有菜单
 * （NavigationService 是 fail-closed 的，无角色时只显示"未绑定岗位"）。
 * 演示数据里的 29 个业务账号虽然填了 department_id / position_id，但没挂角色，
 * 登录后侧栏会是空的——本 Seeder 按 positions.role_names 一次性补齐。
 *
 * 幂等：syncRoles 覆盖式写入，可反复执行。
 */
class PositionRoleSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Role::on('erp')->pluck('name')->all();

        $positions = Position::query()->get();
        $fixed = 0;
        $skipped = 0;

        foreach (User::query()->whereNotNull('position_id')->get() as $user) {
            $position = $positions->firstWhere('id', $user->position_id);
            if (! $position) {
                $skipped++;
                continue;
            }

            $names = $this->roleNames($position->role_names);
            // 只授予库里真实存在的角色，避免拼错把账号变成"有角色但无权限"
            $names = array_values(array_intersect($names, $existing));
            if (! $names) {
                $skipped++;
                continue;
            }

            $user->syncRoles($names);
            $fixed++;
        }

        // 没有岗位的账号（历史遗留）兜底挂综合岗，否则登录后侧栏全空
        $fallback = in_array('role_support_staff', $existing, true) ? 'role_support_staff' : null;
        if ($fallback) {
            $orphans = User::query()->whereNull('position_id')->get()
                ->filter(fn (User $user) => $user->getRoleNames()->isEmpty());
            foreach ($orphans as $user) {
                $user->syncRoles([$fallback]);
                $fixed++;
            }
        }

        // 演示管理员：system_admin 的岗位数据范围是 none（系统管理员不参与业务），
        // 演示时客户/订单详情会被行级范围过滤成 404。这里额外叠加总经理岗，
        // 让"演示管理员"既能做系统配置，也能翻全部业务数据。
        if (in_array('role_gm', $existing, true)) {
            $admins = User::query()->where('username', 'admin')->get();
            foreach ($admins as $user) {
                $roles = $user->getRoleNames()->all();
                if (in_array('system_admin', $roles, true) && ! in_array('role_gm', $roles, true)) {
                    $user->syncRoles(array_values(array_unique([...$roles, 'role_gm'])));
                    $fixed++;
                }
            }
        }

        $this->command?->info("按岗位补齐角色：{$fixed} 个账号已同步，{$skipped} 个跳过（无岗位或无有效角色）");
    }

    /** role_names 可能是 JSON 数组、逗号串或单值 */
    private function roleNames(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw)));
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded)));
        }

        return array_values(array_filter(array_map('trim', explode(',', $text))));
    }
}
