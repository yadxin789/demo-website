<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '研发项目管理')</title>
    <link rel="stylesheet" href="{{ asset('assets/pm/pm.css') }}">
</head>
<body>
    <header class="app-header">
        <div class="app-wrap">
            <a class="app-brand" href="{{ route('pm.root') }}">
                <span class="app-dot"></span>研发项目管理
            </a>
            <span class="app-company">{{ config('products.products.pm.company') }}</span>
        </div>
    </header>

    <main class="app-wrap app-main">
        @yield('content')
    </main>

    <footer class="app-footer">
        <div class="app-wrap">
            演示环境 · {{ config('products.products.pm.company') }} · 演示账号密码统一 123456
        </div>
    </footer>
</body>
</html>