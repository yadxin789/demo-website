<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'POS 收银') | {{ config('shop.name') }}</title>

    @include('shop::partials.theme-head')

    {{-- 本地托管静态资源（离线运行，禁止 CDN） --}}
    <link rel="stylesheet" href="{{ asset(config('shop.assets.bootstrap.css')) }}">
    <link rel="stylesheet" href="{{ asset(config('shop.assets.fontawesome.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/shop/css/shop.css') }}">
    @stack('styles')
</head>
<body class="pos-body">
    <a href="#pos-main" class="skip-link">跳转到收银台</a>

    <div class="pos-shell">
        @yield('content')
    </div>

    <script src="{{ asset(config('shop.assets.bootstrap.js')) }}"></script>
    <script src="{{ asset('assets/shop/js/shop.js') }}"></script>
    @stack('scripts')
</body>
</html>
