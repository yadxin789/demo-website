<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>页面未找到 | {{ config('shop.name') }}</title>
    <link rel="stylesheet" href="{{ asset(config('shop.assets.bootstrap.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/shop/css/shop.css') }}">
</head>
<body>
    <main class="app-main d-flex align-items-center justify-content-center" style="min-block-size:100vh;" role="main">
        <section class="card text-center" style="max-inline-size:28rem;">
            <p class="text-muted"><i class="fa-solid fa-file-circle-question fa-2x"></i></p>
            <h1 class="h4">404 · 页面未找到</h1>
            <p class="text-muted mb-3">您访问的页面不存在或已被移除。</p>
            <a href="{{ url('shop/') }}" class="btn btn-primary"><i class="fa-solid fa-house me-1"></i>返回首页</a>
        </section>
    </main>
</body>
</html>
