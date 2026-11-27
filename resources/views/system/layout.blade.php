<!DOCTYPE html>
<html lang="zh-CN">
@php
    $consoleProducts = \App\Support\System\SystemConsole::PRODUCTS;
    $product = $product ?? null;
    if (! $product || ! isset($consoleProducts[$product])) {
        $seg = request()->segment(1);
        $product = ($seg && isset($consoleProducts[$seg])) ? $seg : explode('.', (string) optional(request()->route())->getName())[0];
    }
    if (! isset($consoleProducts[$product])) {
        $product = 'erp';
    }
    $cfg = $cfg ?? \App\Support\System\SystemConsole::config($product);
    $consoleBase = $consoleBase ?? "/{$product}/admin";
    $themeColor = $themeColor ?? \App\Support\System\SystemConsole::theme($product);
    $currentAdmin = $currentAdmin ?? auth()->user();
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '系统管理') · {{ $cfg['label'] }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/erp/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/erp/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/erp/css/all.min.css') }}">
    <style>
        :root {
            --brand: {{ $themeColor }};
            --side-bg: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            --side-fg: #b9c2d0;
            --side-dim: #6b7891;
            --page-bg: #f7f8fa;
        }
        body { font-family: -apple-system, "Segoe UI", "Microsoft YaHei", "PingFang SC", sans-serif; background: var(--page-bg); color: #1f2937; }
        .sys-side { position: fixed; inset: 0 auto 0 0; width: 236px; background: var(--side-bg); color: var(--side-fg); padding: 18px 12px; overflow-y: auto; z-index: 1030; }
        .sys-side .brand { display: flex; align-items: center; gap: 10px; padding: 4px 8px 14px; border-bottom: 1px solid rgba(255,255,255,.08); margin-bottom: 12px; }
        .sys-side .brand i { color: #fff; font-size: 18px; }
        .sys-side .brand-text { color: #fff; font-weight: 600; font-size: 14px; line-height: 1.2; }
        .sys-side .brand-text small { display: block; color: var(--side-dim); font-weight: 400; font-size: 11px; margin-top: 2px; }
        .sys-side .grp { color: var(--side-dim); font-size: 11px; letter-spacing: .06em; text-transform: uppercase; margin: 14px 8px 6px; }
        .sys-side a.navx { display: flex; align-items: center; gap: 9px; padding: 7px 9px; border-radius: 7px; color: var(--side-fg); font-size: 13.5px; text-decoration: none; }
        .sys-side a.navx:hover { background: rgba(255,255,255,.07); color: #fff; }
        .sys-side a.navx.on { background: rgba(255,255,255,.07); color: #fff; box-shadow: inset 3px 0 0 0 var(--brand); font-weight: 600; }
        .sys-side a.navx i { width: 16px; text-align: center; font-size: 13px; }
        .sys-side .foot { margin-top: 18px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,.08); }
        .sys-side .foot a, .sys-side .foot button { color: var(--side-dim); font-size: 12.5px; }
        .sys-side .foot a:hover, .sys-side .foot button:hover { color: #fff; }
        .sys-main { margin-left: 236px; min-height: 100vh; }
        .sys-top { background: #fff; border-bottom: 1px solid #e9ecef; padding: 13px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1020; }
        .sys-top .t { font-size: 16px; font-weight: 600; }
        .sys-top .s { font-size: 12.5px; color: #6b7280; margin-left: 10px; }
        .sys-badge { background: rgba(0,0,0,.04); color: #374151; border: 1px solid #e5e7eb; border-radius: 999px; font-size: 11.5px; padding: 2px 9px; }
        .sys-body { padding: 22px 24px 40px; }
        .card { border-color: #e9ecef; border-radius: 10px; box-shadow: 0 1px 2px rgba(17,24,39,.04); }
        .card-header { background: #fff; border-bottom-color: #eef0f3; font-weight: 600; font-size: 13.5px; }
        .table > :not(caption) > * > * { padding: .5rem .75rem; font-size: 13px; }
        .stat { background: #fff; border: 1px solid #e9ecef; border-radius: 10px; padding: 14px 16px; height: 100%; }
        .stat .k { font-size: 11.5px; color: #6b7280; }
        .stat .v { font-size: 22px; font-weight: 700; line-height: 1.2; }
        .stat .u { font-size: 11.5px; color: #9ca3af; }
        @media (max-width: 900px) { .sys-side { position: static; width: auto; } .sys-main { margin-left: 0; } }
    </style>
    @stack('styles')
    {{-- 移动端适配（S19）：抽屉式侧栏 + 表格横向滚动 --}}
    <link rel="stylesheet" href="{{ asset('assets/shared/css/responsive.css') }}">
    <script defer src="{{ asset('assets/shared/js/responsive.js') }}"></script>
</head>
<body>
<aside class="sys-side">
    <div class="brand">
        <i class="fa-solid {{ $cfg['icon'] }}"></i>
        <div class="brand-text">{{ $cfg['label'] }}<small>系统管理后台 · 只做管理，不做业务</small></div>
    </div>

    @php($here = '/'.ltrim(request()->path(), '/'))
    <div class="grp">总览</div>
    <a class="navx {{ $here === $consoleBase ? 'on' : '' }}" href="{{ $consoleBase }}"><i class="fa-solid fa-gauge-high"></i>后台首页</a>

    <div class="grp">组织与账号</div>
    <a class="navx {{ str_starts_with($here, $consoleBase.'/users') ? 'on' : '' }}" href="{{ $consoleBase }}/users"><i class="fa-solid fa-user-gear"></i>用户管理</a>
    <a class="navx {{ str_starts_with($here, $consoleBase.'/departments') ? 'on' : '' }}" href="{{ $consoleBase }}/departments"><i class="fa-solid fa-sitemap"></i>部门管理</a>
    <a class="navx {{ str_starts_with($here, $consoleBase.'/positions') ? 'on' : '' }}" href="{{ $consoleBase }}/positions"><i class="fa-solid fa-id-badge"></i>岗位管理</a>
    <a class="navx {{ str_starts_with($here, $consoleBase.'/roles') ? 'on' : '' }}" href="{{ $consoleBase }}/roles"><i class="fa-solid fa-user-shield"></i>角色管理</a>

    <div class="grp">智能与运维</div>
    <a class="navx {{ str_starts_with($here, $consoleBase.'/ai-guide') ? 'on' : '' }}" href="{{ $consoleBase }}/ai-guide"><i class="fa-solid fa-wand-magic-sparkles"></i>AI 导入引导</a>
    <a class="navx {{ str_starts_with($here, $consoleBase.'/ops') ? 'on' : '' }}" href="{{ $consoleBase }}/ops"><i class="fa-solid fa-heart-pulse"></i>运维监控</a>
    <a class="navx {{ str_starts_with($here, $consoleBase.'/settings') ? 'on' : '' }}" href="{{ $consoleBase }}/settings"><i class="fa-solid fa-sliders"></i>系统设置</a>

    @if (! empty($cfg['extras']))
        <div class="grp">扩展维护</div>
        @foreach ($cfg['extras'] as $x)
            <a class="navx" href="{{ $x['url'] }}"><i class="fa-solid {{ $x['icon'] ?? 'fa-folder' }}"></i>{{ $x['label'] }}</a>
        @endforeach
    @endif

    <div class="foot">
        <a class="d-flex align-items-center gap-2 px-2 py-1" href="/"><i class="fa-solid fa-house"></i>返回门户</a>
        <form method="POST" action="/{{ $product }}/logout" class="px-2 py-1">
            @csrf
            <button type="submit" class="btn btn-link p-0 border-0"><i class="fa-solid fa-right-from-bracket me-1"></i>退出登录</button>
        </form>
    </div>
</aside>

<div class="sys-main">
    <div class="sys-top">
        <div>
            <span class="t">@yield('title', '后台首页')</span>
            <span class="s">@yield('sub', '')</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="sys-badge">系统管理 · {{ $cfg['short'] }}</span>
            <span class="text-muted small">{{ $currentAdmin->name ?? '管理员' }}</span>
        </div>
    </div>
    <div class="sys-body">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @yield('content')
    </div>
</div>

<script src="{{ asset('assets/erp/js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
@yield('scripts')
</body>
</html>
