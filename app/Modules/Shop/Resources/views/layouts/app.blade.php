<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '百货零售 ERP')</title>
    <link rel="stylesheet" href="{{ asset('assets/shop/shop.css') }}">
</head>
<body>
    <header class="app-header">
        <div class="app-wrap">
            <a class="app-brand" href="{{ route('shop.root') }}">
                <span class="app-dot"></span>百货零售 ERP
            </a>
            <span class="app-company">{{ config('products.products.shop.company') }}</span>
        </div>
    </header>

    <main class="app-wrap app-main">
        @yield('content')
    </main>

    <footer class="app-footer">
        <div class="app-wrap">
            演示环境 · {{ config('products.products.shop.company') }} · 演示账号密码统一 123456
        </div>
    </footer>
</body>
</html>