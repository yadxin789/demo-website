<!doctype html>
<html lang="zh-CN">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/hr/favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '工作台') · 人事管理 HR</title>
    <link rel="stylesheet" href="{{ asset('assets/hr/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/hr/vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/hr/hr.css') }}">
    @stack('styles')
    {{-- 五产品统一外框皮肤（S10）：放在最后加载，覆盖本产品自带样式 --}}
    <style>:root { --brand: {{ config('products.products.hr.theme.primary') }}; --brand-dark: {{ config('products.products.hr.theme.accent') }}; }</style>
    <link rel="stylesheet" href="{{ asset('assets/shared/css/shell.css') }}">
</head>
<body class="hr-body">
<div class="hr-shell">
    <aside class="hr-side">
        <div class="hr-side-brand">
            <span class="mark">HR</span>
            <span class="txt">
                <b>华瑞集团（控股）</b>
                <span>人力共享服务中心</span>
            </span>
        </div>

        @php($me = auth('hr')->user())
        <nav class="hr-nav">
            <div class="grp">总览</div>
            <a class="{{ request()->routeIs('hr.dashboard') ? 'on' : '' }}" href="{{ route('hr.dashboard') }}">
                <i class="fa-solid fa-gauge-high"></i> 人力看板
            </a>

            <div class="grp">编制</div>
            <a class="{{ request()->routeIs('hr.org.headcounts.*') ? 'on' : '' }}" href="{{ route('hr.org.headcounts.index') }}">
                <i class="fa-solid fa-users-viewfinder"></i> 编制缺口
            </a>

            <div class="grp">员工</div>
            <a class="{{ request()->routeIs('hr.employees.index') || request()->routeIs('hr.employees.show') || request()->routeIs('hr.employees.edit') ? 'on' : '' }}" href="{{ route('hr.employees.index') }}">
                <i class="fa-solid fa-address-book"></i> 员工档案
            </a>
            <a class="{{ request()->routeIs('hr.employees.contracts*') ? 'on' : '' }}" href="{{ route('hr.employees.contracts') }}">
                <i class="fa-solid fa-file-signature"></i> 合同与到期
            </a>
            <a class="{{ request()->routeIs('hr.employees.movements*') ? 'on' : '' }}" href="{{ route('hr.employees.movements') }}">
                <i class="fa-solid fa-right-left"></i> 异动记录
            </a>

            <div class="grp">考勤与假期</div>
            <a class="{{ request()->routeIs('hr.attendance.records*') ? 'on' : '' }}" href="{{ route('hr.attendance.records') }}">
                <i class="fa-solid fa-clock"></i> 打卡记录
            </a>
            <a class="{{ request()->routeIs('hr.attendance.exceptions*') ? 'on' : '' }}" href="{{ route('hr.attendance.exceptions') }}">
                <i class="fa-solid fa-triangle-exclamation"></i> 考勤异常
            </a>
            <a class="{{ request()->routeIs('hr.attendance.overtimes*') ? 'on' : '' }}" href="{{ route('hr.attendance.overtimes') }}">
                <i class="fa-solid fa-hourglass-half"></i> 加班
            </a>
            <a class="{{ request()->routeIs('hr.attendance.shifts*') ? 'on' : '' }}" href="{{ route('hr.attendance.shifts') }}">
                <i class="fa-solid fa-calendar-day"></i> 班次
            </a>
            <a class="{{ request()->routeIs('hr.leaves.index') || request()->routeIs('hr.leaves.create') ? 'on' : '' }}" href="{{ route('hr.leaves.index') }}">
                <i class="fa-solid fa-calendar-check"></i> 请假审批
            </a>
            <a class="{{ request()->routeIs('hr.leaves.balances*') ? 'on' : '' }}" href="{{ route('hr.leaves.balances') }}">
                <i class="fa-solid fa-wallet"></i> 假期余额
            </a>

            <div class="grp">薪资</div>
            <a class="{{ request()->routeIs('hr.payroll.runs.*') ? 'on' : '' }}" href="{{ route('hr.payroll.runs.index') }}">
                <i class="fa-solid fa-money-check-dollar"></i> 工资批次
            </a>
            <a class="{{ request()->routeIs('hr.payroll.structures*') ? 'on' : '' }}" href="{{ route('hr.payroll.structures') }}">
                <i class="fa-solid fa-table-list"></i> 薪资结构
            </a>
            <a class="{{ request()->routeIs('hr.payroll.my') ? 'on' : '' }}" href="{{ route('hr.payroll.my') }}">
                <i class="fa-solid fa-receipt"></i> 我的工资条
            </a>

            <div class="grp">招聘与绩效</div>
            <a class="{{ request()->routeIs('hr.recruit.requisitions*') ? 'on' : '' }}" href="{{ route('hr.recruit.requisitions') }}">
                <i class="fa-solid fa-bullhorn"></i> 招聘需求
            </a>
            <a class="{{ request()->routeIs('hr.recruit.candidates*') ? 'on' : '' }}" href="{{ route('hr.recruit.candidates') }}">
                <i class="fa-solid fa-user-plus"></i> 候选人
            </a>
            <a class="{{ request()->routeIs('hr.recruit.interviews*') ? 'on' : '' }}" href="{{ route('hr.recruit.interviews') }}">
                <i class="fa-solid fa-comments"></i> 面试记录
            </a>
            <a class="{{ request()->routeIs('hr.performance.cycles*') ? 'on' : '' }}" href="{{ route('hr.performance.cycles') }}">
                <i class="fa-solid fa-flag-checkered"></i> 绩效周期
            </a>
            <a class="{{ request()->routeIs('hr.performance.reviews*') ? 'on' : '' }}" href="{{ route('hr.performance.reviews') }}">
                <i class="fa-solid fa-star-half-stroke"></i> 绩效评分
            </a>
            <a class="{{ request()->routeIs('hr.performance.results*') ? 'on' : '' }}" href="{{ route('hr.performance.results') }}">
                <i class="fa-solid fa-ranking-star"></i> 绩效结果
            </a>

            <div class="grp">报表与工具</div>
            <a class="{{ request()->routeIs('hr.reports.headcount') ? 'on' : '' }}" href="{{ route('hr.reports.headcount') }}">
                <i class="fa-solid fa-chart-pie"></i> 人数结构
            </a>
            <a class="{{ request()->routeIs('hr.reports.attendance') ? 'on' : '' }}" href="{{ route('hr.reports.attendance') }}">
                <i class="fa-solid fa-chart-column"></i> 考勤汇总
            </a>
            <a class="{{ request()->routeIs('hr.reports.payroll') ? 'on' : '' }}" href="{{ route('hr.reports.payroll') }}">
                <i class="fa-solid fa-chart-line"></i> 人力成本
            </a>
            <a class="{{ request()->routeIs('hr.import.*') ? 'on' : '' }}" href="{{ route('hr.import.index') }}">
                <i class="fa-solid fa-file-import"></i> Excel 导入导出
            </a>

            @if ($me?->hasRole('admin'))
                <div class="grp">系统管理</div>
                <a class="{{ request()->routeIs('hr.org.departments.*') ? 'on' : '' }}" href="{{ route('hr.org.departments.index') }}">
                    <i class="fa-solid fa-sitemap"></i> 部门管理
                </a>
                <a class="{{ request()->routeIs('hr.org.positions.*') ? 'on' : '' }}" href="{{ route('hr.org.positions.index') }}">
                    <i class="fa-solid fa-id-badge"></i> 岗位管理
                </a>
                <a class="{{ request()->routeIs('hr.system.users.*') ? 'on' : '' }}" href="{{ route('hr.system.users.index') }}">
                    <i class="fa-solid fa-users"></i> 账号管理
                </a>
                <a class="{{ request()->routeIs('hr.system.roles.*') ? 'on' : '' }}" href="{{ route('hr.system.roles.index') }}">
                    <i class="fa-solid fa-user-shield"></i> 角色权限
                </a>
                <a class="{{ request()->routeIs('hr.system.notifications*') ? 'on' : '' }}" href="{{ route('hr.system.notifications') }}">
                    <i class="fa-solid fa-bell"></i> 站内通知
                </a>
            @endif
        </nav>
    </aside>

    <div class="hr-main">
        <div class="hr-topbar">
            <div>
                <div class="ttl">@yield('title', '工作台')</div>
                <div class="sub">@yield('sub', '华瑞集团（控股）· 演示环境')</div>
            </div>
            <div class="who">
                <span>考勤期间：<b>{{ now()->format('Y-m') }}</b></span>
                <span><b>{{ auth('hr')->user()?->name ?? '未登录' }}</b></span>
                <form method="post" action="{{ route('hr.logout') }}" style="margin:0">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary" type="submit">退出</button>
                </form>
            </div>
        </div>

        <div class="hr-content">
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

<script src="{{ asset('assets/hr/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>

