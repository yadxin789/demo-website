<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/shop/favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('shop.name')) | {{ config('shop.name') }}</title>

    @include('shop::partials.theme-head')

    {{-- 本地托管静态资源（离线运行，禁止 CDN） --}}
    <link rel="stylesheet" href="{{ asset(config('shop.assets.bootstrap.css')) }}">
    <link rel="stylesheet" href="{{ asset(config('shop.assets.fontawesome.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/shop/css/shop.css') }}">
    @stack('styles')
    {{-- 五产品统一外框皮肤（S10）：放在最后加载，覆盖本产品自带样式 --}}
    <style>:root { --brand: {{ config('products.products.shop.theme.primary') }}; --brand-dark: {{ config('products.products.shop.theme.accent') }}; }</style>
    <link rel="stylesheet" href="{{ asset('assets/shared/css/shell.css') }}">
    {{-- 移动端适配（S19）：抽屉式侧栏 + 表格横向滚动 --}}
    <link rel="stylesheet" href="{{ asset('assets/shared/css/responsive.css') }}">
    <script defer src="{{ asset('assets/shared/js/responsive.js') }}"></script>
</head>
<body>
    <a href="#app-main" class="skip-link">跳转到主内容</a>

    <div class="app-shell">
        @include('shop::partials.sidebar')

        <div class="d-flex flex-column" style="min-block-size:100vh;">
            @include('shop::partials.navbar')

            <main id="app-main" class="app-main" tabindex="-1">
                @hasSection('content')
                    @yield('content')
                @else
                    @yield('main')
                @endif
            </main>

            @include('shop::partials.footer')
        </div>
    </div>

    <script src="{{ asset(config('shop.assets.bootstrap.js')) }}"></script>
    <script src="{{ asset(config('shop.assets.qrcode.js')) }}"></script>
    <script src="{{ asset(config('shop.assets.jsqr.js')) }}"></script>
    <script>
        window.ERP_SCAN = {
            resolveUrl: @json(route('shop.scan.resolve')),
            csrf: @json(csrf_token())
        };
    </script>
    <script src="{{ asset('assets/shop/js/scan.js') }}"></script>
    <script src="{{ asset('assets/shop/js/shop.js') }}"></script>
    @stack('scripts')
</body>
</html>

