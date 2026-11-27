<!doctype html>
<html lang="zh-CN">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/mes/favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '工作台') · 智能制造 MES</title>
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/mes/mes.css') }}">
    @stack('styles')
    {{-- 五产品统一外框皮肤（S10）：放在最后加载，覆盖本产品自带样式 --}}
    <style>:root { --brand: {{ config('products.products.mes.theme.primary') }}; --brand-dark: {{ config('products.products.mes.theme.accent') }}; }</style>
    <link rel="stylesheet" href="{{ asset('assets/shared/css/shell.css') }}">
</head>
<body class="mes-body">
<div class="mes-shell">
    <aside class="mes-side">
        <div class="mes-side-brand">
            <span class="mark">MES</span>
            <span class="txt">
                <b>精工精密制造</b>
                <span>苏州 · 一号车间</span>
            </span>
        </div>

        @php($me = auth('mes')->user())
        <nav class="mes-nav">
            <div class="grp">现场</div>
            <a class="{{ request()->routeIs('mes.dashboard') ? 'on' : '' }}" href="{{ route('mes.dashboard') }}">
                <i class="fa-solid fa-gauge-high"></i> 工作台
            </a>
            <a class="{{ request()->routeIs('mes.my-tasks.*') ? 'on' : '' }}" href="{{ route('mes.my-tasks.index') }}">
                <i class="fa-solid fa-person-digging"></i> 我的任务
            </a>
            <a class="{{ request()->routeIs('mes.work-orders.*') ? 'on' : '' }}" href="{{ route('mes.work-orders.index') }}">
                <i class="fa-solid fa-clipboard-list"></i> 工单
            </a>
            <a class="{{ request()->routeIs('mes.aps.*') ? 'on' : '' }}" href="{{ route('mes.aps.index') }}">
                <i class="fa-solid fa-calendar-days"></i> 排产计划
            </a>
            <a class="{{ request()->routeIs('mes.reports.index') ? 'on' : '' }}" href="{{ route('mes.reports.index') }}">
                <i class="fa-solid fa-clock-rotate-left"></i> 报工记录
            </a>
            <a class="{{ request()->routeIs('mes.issues.*') ? 'on' : '' }}" href="{{ route('mes.issues.index') }}">
                <i class="fa-solid fa-boxes-stacked"></i> 领料发料
            </a>
            <a class="{{ request()->routeIs('mes.receipts.*', 'mes.work-orders.receipts.*') ? 'on' : '' }}" href="{{ route('mes.receipts.index') }}">
                <i class="fa-solid fa-warehouse"></i> 完工入库
            </a>
            <a class="{{ request()->routeIs('mes.trace.*') ? 'on' : '' }}" href="{{ route('mes.trace.index') }}">
                <i class="fa-solid fa-barcode"></i> 批次追溯
            </a>
            <a class="{{ request()->routeIs('mes.kanban') ? 'on' : '' }}" href="{{ route('mes.kanban') }}">
                <i class="fa-solid fa-tv"></i> 车间看板
            </a>

            <div class="grp">报表</div>
            <a class="{{ request()->routeIs('mes.reports.output') ? 'on' : '' }}" href="{{ route('mes.reports.output') }}">
                <i class="fa-solid fa-chart-column"></i> 产量趋势
            </a>
            <a class="{{ request()->routeIs('mes.reports.yield') ? 'on' : '' }}" href="{{ route('mes.reports.yield') }}">
                <i class="fa-solid fa-percent"></i> 良率分析
            </a>
            <a class="{{ request()->routeIs('mes.reports.labor') ? 'on' : '' }}" href="{{ route('mes.reports.labor') }}">
                <i class="fa-solid fa-gauge-simple-high"></i> 工时效率
            </a>
            <a class="{{ request()->routeIs('mes.reports.oee') ? 'on' : '' }}" href="{{ route('mes.reports.oee') }}">
                <i class="fa-solid fa-gauge"></i> OEE
            </a>

            @if ($me?->hasAnyPermission(['master.view', 'master.manage']))
                <div class="grp">主数据</div>
                <a class="{{ request()->routeIs('mes.master.materials.*') ? 'on' : '' }}" href="{{ route('mes.master.materials.index') }}">
                    <i class="fa-solid fa-boxes-stacked"></i> 物料
                </a>
                <a class="{{ request()->routeIs('mes.master.boms.*') ? 'on' : '' }}" href="{{ route('mes.master.boms.index') }}">
                    <i class="fa-solid fa-sitemap"></i> BOM
                </a>
                <a class="{{ request()->routeIs('mes.master.routings.*') ? 'on' : '' }}" href="{{ route('mes.master.routings.index') }}">
                    <i class="fa-solid fa-route"></i> 工艺路线
                </a>
                <a class="{{ request()->routeIs('mes.master.work-centers.*') ? 'on' : '' }}" href="{{ route('mes.master.work-centers.index') }}">
                    <i class="fa-solid fa-industry"></i> 工作中心
                </a>
                <a class="{{ request()->routeIs('mes.master.shifts.*') ? 'on' : '' }}" href="{{ route('mes.master.shifts.index') }}">
                    <i class="fa-solid fa-clock"></i> 班次
                </a>
            @endif

            @if ($me?->hasAnyPermission(['quality.view', 'quality.manage', 'equipment.view', 'equipment.manage']))
                <div class="grp">质量与设备</div>
            @endif
            @if ($me?->hasAnyPermission(['quality.view', 'quality.manage']))
                <a class="{{ request()->routeIs('mes.quality.inspections.*') ? 'on' : '' }}" href="{{ route('mes.quality.inspections.index') }}">
                    <i class="fa-solid fa-clipboard-check"></i> 检验单
                </a>
                <a class="{{ request()->routeIs('mes.quality.templates.*') ? 'on' : '' }}" href="{{ route('mes.quality.templates.index') }}">
                    <i class="fa-solid fa-list-check"></i> 检验模板
                </a>
                <a class="{{ request()->routeIs('mes.quality.defects.*') ? 'on' : '' }}" href="{{ route('mes.quality.defects.index') }}">
                    <i class="fa-solid fa-triangle-exclamation"></i> 不良记录
                </a>
            @endif
            @if ($me?->hasAnyPermission(['equipment.view', 'equipment.manage']))
                <a class="{{ request()->routeIs('mes.equipment.*') ? 'on' : '' }}" href="{{ route('mes.equipment.index') }}">
                    <i class="fa-solid fa-screwdriver-wrench"></i> 设备
                </a>
                <a class="{{ request()->routeIs('mes.maintenance.*') ? 'on' : '' }}" href="{{ route('mes.maintenance.plans') }}">
                    <i class="fa-solid fa-calendar-check"></i> 保养计划
                </a>
            @endif

            @if ($me?->hasAnyPermission(['import.run']))
                <div class="grp">导入导出</div>
                <a class="{{ request()->routeIs('mes.import.*') ? 'on' : '' }}" href="{{ route('mes.import.index') }}">
                    <i class="fa-solid fa-file-import"></i> Excel 导入
                </a>
            @endif

            @if ($me?->hasRole('admin'))
                <div class="grp">系统管理</div>
                <a class="{{ request()->routeIs('mes.system.departments.*') ? 'on' : '' }}" href="{{ route('mes.system.departments.index') }}">
                    <i class="fa-solid fa-sitemap"></i> 部门管理
                </a>
                <a class="{{ request()->routeIs('mes.system.positions.*') ? 'on' : '' }}" href="{{ route('mes.system.positions.index') }}">
                    <i class="fa-solid fa-id-badge"></i> 岗位管理
                </a>
                <a class="{{ request()->routeIs('mes.system.users.*') ? 'on' : '' }}" href="{{ route('mes.system.users.index') }}">
                    <i class="fa-solid fa-users"></i> 账号管理
                </a>
                <a class="{{ request()->routeIs('mes.system.roles.*') ? 'on' : '' }}" href="{{ route('mes.system.roles.index') }}">
                    <i class="fa-solid fa-user-shield"></i> 角色权限
                </a>
                <a class="{{ request()->routeIs('mes.system.notifications.*') ? 'on' : '' }}" href="{{ route('mes.system.notifications.index') }}">
                    <i class="fa-solid fa-bell"></i> 站内通知
                </a>
            @endif
        </nav>
    </aside>

    <div class="mes-main">
        <div class="mes-topbar">
            <div>
                <div class="ttl">@yield('title', '工作台')</div>
                <div class="sub">@yield('sub', '精工精密制造（苏州）· 演示环境')</div>
            </div>
            <div class="who">
                <span>当前班次：<b>白班</b></span>
                <span><b>{{ auth('mes')->user()?->name ?? '未登录' }}</b></span>
                <form method="post" action="{{ route('mes.logout') }}" style="margin:0">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary" type="submit">退出</button>
                </form>
            </div>
        </div>

        <div class="mes-content">
            @if (session('status'))
                <div class="alert alert-success py-2">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger py-2">{{ session('error') }}</div>
            @endif

            @yield('content')
        </div>
    </div>
</div>

<script src="{{ asset('assets/mes/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>

