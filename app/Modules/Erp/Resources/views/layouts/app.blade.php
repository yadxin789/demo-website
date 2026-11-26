<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '电子制造 ERP')</title>
    <link rel="stylesheet" href="{{ asset('assets/erp/erp.css') }}">
</head>
<body>
    <header class="app-header">
        <div class="app-wrap">
            <a class="app-brand" href="{{ route('erp.root') }}">
                <span class="app-dot"></span>电子制造 ERP
            </a>
            <span class="app-company">{{ config('products.products.erp.company') }}</span>
        </div>
    </header>

    <main class="app-wrap app-main">
        @yield('content')
    </main>

    <footer class="app-footer">
        <div class="app-wrap">
            演示环境 · {{ config('products.products.erp.company') }} · 演示账号密码统一 123456
        </div>
    </footer>
</body>
</html>