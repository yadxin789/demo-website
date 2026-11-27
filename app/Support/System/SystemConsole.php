<?php

namespace App\Support\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 系统管理后台（Console）的统一数据适配层。
 *
 * 五个产品的库结构不同（用户/部门/岗位/角色的表名与列名各不相同），
 * 这里用「探测列名 + 归一化」的方式给出统一的读取接口，
 * 让后台页面共用一套视图与控制器。
 */
class SystemConsole
{
    /** 产品静态配置 */
    public const PRODUCTS = [
        'erp' => [
            'label' => '电子制造 ERP',
            'short' => 'ERP',
            'icon' => 'fa-industry',
            'url' => '/erp/admin',
            'login' => '/erp/login',
            'extras' => [
                ['label' => '主数据维护', 'url' => '/erp/admin/master-data', 'icon' => 'fa-database'],
                ['label' => '角色权限矩阵', 'url' => '/erp/admin/permissions', 'icon' => 'fa-key'],
                ['label' => '系统可观测', 'url' => '/erp/system/observability', 'icon' => 'fa-chart-line'],
            ],
        ],
        'pm' => [
            'label' => '研发项目管理',
            'short' => 'PM',
            'icon' => 'fa-diagram-project',
            'url' => '/pm/admin',
            'login' => '/pm/login',
            'extras' => [
                ['label' => '状态字典', 'url' => '/pm/admin/statuses', 'icon' => 'fa-list-check'],
                ['label' => '任务类型', 'url' => '/pm/admin/task-types', 'icon' => 'fa-tags'],
                ['label' => '操作日志', 'url' => '/pm/admin/operation-logs', 'icon' => 'fa-clipboard-list'],
                ['label' => '服务与备份', 'url' => '/pm/admin/services', 'icon' => 'fa-server'],
            ],
        ],
        'shop' => [
            'label' => '百货零售 ERP',
            'short' => 'SHOP',
            'icon' => 'fa-store',
            'url' => '/shop/admin',
            'login' => '/shop/login',
            'extras' => [
                ['label' => '审计日志', 'url' => '/shop/audit-logs', 'icon' => 'fa-clipboard-list'],
                ['label' => '自动化任务', 'url' => '/shop/automation', 'icon' => 'fa-robot'],
                ['label' => '数据备份', 'url' => '/shop/backups', 'icon' => 'fa-database'],
            ],
        ],
        'mes' => [
            'label' => '智能制造 MES',
            'short' => 'MES',
            'icon' => 'fa-gears',
            'url' => '/mes/admin',
            'login' => '/mes/login',
            'extras' => [
                ['label' => '消息通知', 'url' => '/mes/system/notifications', 'icon' => 'fa-bell'],
            ],
        ],
        'hr' => [
            'label' => '人事管理 HR',
            'short' => 'HR',
            'icon' => 'fa-users',
            'url' => '/hr/admin',
            'login' => '/hr/login',
            'extras' => [
                ['label' => '消息通知', 'url' => '/hr/system/notifications', 'icon' => 'fa-bell'],
            ],
        ],
    ];

    public static function config(string $product): array
    {
        return self::PRODUCTS[$product] ?? self::PRODUCTS['erp'];
    }

    public static function connection(string $product): string
    {
        return $product;
    }

    public static function guard(string $product): string
    {
        return $product;
    }

    public static function theme(string $product): string
    {
        return (string) config("products.products.{$product}.theme.primary", '#2d6cdf');
    }

    /** 该产品数据库里是否存在某表 */
    public static function hasTable(string $product, string $table): bool
    {
        return Schema::connection($product)->hasTable($table);
    }

    /** 探测某表中最先命中的列名 */
    public static function col(string $product, string $table, array $candidates): ?string
    {
        $cols = Schema::connection($product)->getColumnListing($table);
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }

        return null;
    }

    /** 用户表状态列：返回 [列名, 活跃时的取值] */
    public static function userStatus(string $product): array
    {
        if (self::hasTable($product, 'users')) {
            if (self::col($product, 'users', ['is_active'])) {
                return ['is_active', 1];
            }
            $col = self::col($product, 'users', ['status']);
            if ($col) {
                $sample = DB::connection($product)->table('users')->value($col);
                return [$col, is_numeric($sample) ? 1 : 'active'];
            }
        }

        return ['status', 1];
    }

    /** 组织（部门/岗位）表的规范化列映射 */
    public static function orgMeta(string $product, string $table): array
    {
        return [
            'name' => self::col($product, $table, ['dept_name', 'name', 'pos_name', 'title']),
            'code' => self::col($product, $table, ['dept_code', 'code', 'pos_code']),
            'sort' => self::col($product, $table, ['sort_order', 'sort']),
            'status' => self::col($product, $table, ['status']),
            'parent' => self::col($product, $table, ['parent_id']),
            'department' => self::col($product, $table, ['department_id']),
        ];
    }

    /** 角色列表：统一成 [id, name, code, users, permissions] */
    public static function roles(string $product): array
    {
        $conn = DB::connection($product);

        if (self::hasTable($product, 'spatie_roles')) {
            $roles = $conn->table('spatie_roles')->orderBy('id')->get();
            $userCounts = self::hasTable($product, 'model_has_roles')
                ? $conn->table('model_has_roles')->select('role_id', DB::raw('count(*) as c'))->groupBy('role_id')->pluck('c', 'role_id')
                : collect();
            $permCounts = self::hasTable($product, 'role_has_permissions')
                ? $conn->table('role_has_permissions')->select('role_id', DB::raw('count(*) as c'))->groupBy('role_id')->pluck('c', 'role_id')
                : collect();

            return $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->name,
                'guard' => $r->guard_name ?? null,
                'users' => (int) ($userCounts[$r->id] ?? 0),
                'permissions' => (int) ($permCounts[$r->id] ?? 0),
            ])->all();
        }

        if (self::hasTable($product, 'roles')) {
            $roles = $conn->table('roles')->orderBy('id')->get();
            $userCounts = self::col($product, 'users', ['role_id'])
                ? $conn->table('users')->select('role_id', DB::raw('count(*) as c'))->groupBy('role_id')->pluck('c', 'role_id')
                : collect();

            return $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code ?? $r->name,
                'guard' => null,
                'users' => (int) ($userCounts[$r->id] ?? 0),
                'permissions' => 0,
            ])->all();
        }

        return [];
    }

    /** 用户行：统一成 [id, name, account, email, dept, position, role, active, last_login] */
    public static function users(string $product, ?string $keyword = null, int $limit = 200): array
    {
        $conn = DB::connection($product);
        $table = 'users';
        $name = self::col($product, $table, ['name', 'username']);
        $account = self::col($product, $table, ['username', 'email', 'employee_no']);
        $email = self::col($product, $table, ['email']);
        $dept = self::col($product, $table, ['department_id']);
        $pos = self::col($product, $table, ['position_id']);
        $roleCode = self::col($product, $table, ['role_code']);
        $roleId = self::col($product, $table, ['role_id']);
        $lastLogin = self::col($product, $table, ['last_login_at']);
        [$statusCol, $activeVal] = self::userStatus($product);

        $q = $conn->table($table)->orderBy('id');
        if ($keyword !== null && $keyword !== '') {
            $q->where(function ($w) use ($name, $account, $email, $keyword) {
                foreach (array_filter([$name, $account, $email]) as $c) {
                    $w->orWhere($c, 'like', "%{$keyword}%");
                }
            });
        }
        $rows = $q->limit($limit)->get();

        $deptNameCol = self::hasTable($product, 'departments') ? self::orgMeta($product, 'departments')['name'] : null;
        $deptNames = $deptNameCol ? $conn->table('departments')->pluck($deptNameCol, 'id') : collect();
        $posNameCol = self::hasTable($product, 'positions') ? self::orgMeta($product, 'positions')['name'] : null;
        $posNames = $posNameCol ? $conn->table('positions')->pluck($posNameCol, 'id') : collect();
        $roleNames = collect(self::roles($product))->pluck('name', 'id');

        return $rows->map(function ($u) use ($name, $account, $email, $dept, $pos, $roleCode, $roleId, $lastLogin, $statusCol, $activeVal, $deptNames, $posNames, $roleNames) {
            $statusValue = $u->{$statusCol} ?? null;
            $active = is_numeric($activeVal) ? (int) $statusValue === (int) $activeVal : (string) $statusValue === (string) $activeVal;

            return [
                'id' => $u->id,
                'name' => $name ? ($u->{$name} ?? '') : '',
                'account' => $account ? ($u->{$account} ?? '') : '',
                'email' => $email ? ($u->{$email} ?? '') : '',
                'department' => $dept ? ($deptNames[$u->{$dept}] ?? '—') : '—',
                'position' => $pos ? ($posNames[$u->{$pos}] ?? '—') : '—',
                'role' => $roleCode ? ($u->{$roleCode} ?? '—') : ($roleId ? ($roleNames[$u->{$roleId}] ?? '—') : '—'),
                'active' => $active,
                'last_login' => $lastLogin ? $u->{$lastLogin} : null,
            ];
        })->all();
    }

    /** 部门行 */
    public static function departments(string $product): array
    {
        if (! self::hasTable($product, 'departments')) {
            return [];
        }
        $meta = self::orgMeta($product, 'departments');
        $rows = DB::connection($product)->table('departments')->orderBy($meta['sort'] ?? 'id')->get();

        return $rows->map(fn ($d) => [
            'id' => $d->id,
            'code' => $meta['code'] ? ($d->{$meta['code']} ?? '') : '',
            'name' => $meta['name'] ? ($d->{$meta['name']} ?? '') : '',
            'sort' => $meta['sort'] ? ($d->{$meta['sort']} ?? 0) : 0,
            'status' => $meta['status'] ? $d->{$meta['status']} : null,
        ])->all();
    }

    /** 岗位行 */
    public static function positions(string $product): array
    {
        if (! self::hasTable($product, 'positions')) {
            return [];
        }
        $meta = self::orgMeta($product, 'positions');
        $deptNameCol = self::hasTable($product, 'departments') ? self::orgMeta($product, 'departments')['name'] : null;
        $deptNames = $deptNameCol
            ? DB::connection($product)->table('departments')->pluck($deptNameCol, 'id')
            : collect();
        $rows = DB::connection($product)->table('positions')->orderBy('id')->get();

        return $rows->map(fn ($p) => [
            'id' => $p->id,
            'code' => $meta['code'] ? ($p->{$meta['code']} ?? '') : '',
            'name' => $meta['name'] ? ($p->{$meta['name']} ?? '') : '',
            'department' => $meta['department'] ? ($deptNames[$p->{$meta['department']}] ?? '—') : '—',
            'status' => $meta['status'] ? $p->{$meta['status']} : null,
        ])->all();
    }

    /** 概览数字 */
    public static function stats(string $product): array
    {
        $conn = DB::connection($product);

        $tables = $conn->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tableCount = count($tables);

        return [
            'users' => self::hasTable($product, 'users') ? $conn->table('users')->count() : 0,
            'departments' => self::hasTable($product, 'departments') ? $conn->table('departments')->count() : 0,
            'positions' => self::hasTable($product, 'positions') ? $conn->table('positions')->count() : 0,
            'roles' => count(self::roles($product)),
            'permissions' => self::hasTable($product, 'permissions') ? $conn->table('permissions')->count() : 0,
            'tables' => $tableCount,
            'db_file' => self::databaseFile($product),
            'db_size' => self::databaseSize($product),
        ];
    }

    public static function databaseFile(string $product): string
    {
        $path = config("database.connections.{$product}.database");

        return is_string($path) ? $path : '';
    }

    public static function databaseSize(string $product): int
    {
        $file = self::databaseFile($product);

        return ($file && is_file($file)) ? (int) filesize($file) : 0;
    }

    /** 是否具备后台管理权限（演示口径：账号 admin 或角色名含 admin） */
    public static function canAdmin(string $product, $user): bool
    {
        if (! $user) {
            return false;
        }
        $username = (string) ($user->username ?? '');
        if ($username === 'admin') {
            return true;
        }
        $roleCode = (string) ($user->role_code ?? '');
        if ($roleCode !== '' && preg_match('/admin|super|manager|sys/', $roleCode)) {
            return true;
        }
        try {
            if (method_exists($user, 'getRoleNames')) {
                foreach ($user->getRoleNames() as $r) {
                    if (preg_match('/admin|super|sys/', (string) $r)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
            // 忽略：部分产品的权限模型未启用 spatie
        }

        return false;
    }
}
