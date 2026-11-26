<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>无权限访问 | {{ config('shop.name') }}</title>
    <link rel="stylesheet" href="{{ asset(config('shop.assets.bootstrap.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/shop/css/shop.css') }}">
</head>
<body>
    <main class="app-main d-flex align-items-center justify-content-center" style="min-block-size:100vh;" role="main">
        <section class="card text-center" style="max-inline-size:28rem;">
            <p class="text-muted"><i class="fa-solid fa-ban fa-2x"></i></p>
            <h1 class="h4">403 · 无权限访问</h1>
            <p class="text-muted mb-3">您没有访问该内容的权限，如有需要请联系管理员。</p>
            <a href="{{ url('shop/') }}" class="btn btn-primary"><i class="fa-solid fa-house me-1"></i>返回首页</a>
        </section>
    </main>
</body>
</html>
