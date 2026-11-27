<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '工作台') · 智能制造 MES</title>
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/mes/mes.css') }}">
    @stack('styles')
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
            <a class="{{ request()->routeIs('mes.reports.*') ? 'on' : '' }}" href="{{ route('mes.reports.index') }}">
                <i class="fa-solid fa-clock-rotate-left"></i> 报工记录
            </a>
            <a class="{{ request()->routeIs('mes.issues.*') ? 'on' : '' }}" href="{{ route('mes.issues.index') }}">
                <i class="fa-solid fa-boxes-stacked"></i> 领料发料
            </a>
            <a class="{{ request()->routeIs('mes.kanban') ? 'on' : '' }}" href="{{ route('mes.kanban') }}">
                <i class="fa-solid fa-tv"></i> 车间看板
            </a>

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

            <div class="grp">质量与设备</div>
            <a class="{{ request()->routeIs('mes.quality.*') ? 'on' : '' }}" href="{{ route('mes.quality.inspections.index') }}">
                <i class="fa-solid fa-clipboard-check"></i> 检验
            </a>
            <a class="{{ request()->routeIs('mes.equipment.*') ? 'on' : '' }}" href="{{ route('mes.equipment.index') }}">
                <i class="fa-solid fa-screwdriver-wrench"></i> 设备
            </a>

            <div class="grp">导入导出</div>
            <a class="{{ request()->routeIs('mes.import.*') ? 'on' : '' }}" href="{{ route('mes.import.index') }}">
                <i class="fa-solid fa-file-import"></i> Excel 导入
            </a>

            <div class="grp">系统</div>
            <a class="{{ request()->routeIs('mes.system.users.*') ? 'on' : '' }}" href="{{ route('mes.system.users.index') }}">
                <i class="fa-solid fa-users"></i> 账号
            </a>
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
