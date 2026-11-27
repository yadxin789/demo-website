<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/shop/favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登录 | {{ config('shop.name') }}</title>
    <link rel="stylesheet" href="{{ asset(config('shop.assets.bootstrap.css')) }}">
    <link rel="stylesheet" href="{{ asset(config('shop.assets.fontawesome.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/shop/css/shop.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/shared/css/responsive.css') }}">
</head>
<body>
    <main class="d-flex align-items-center justify-content-center" style="min-block-size:100vh;" role="main">
        <section class="card" style="inline-size:100%;max-inline-size:24rem;" aria-labelledby="login-heading">
            <div class="text-center mb-3">
                <i class="fa-solid fa-store fa-2x text-primary"></i>
                <h1 id="login-heading" class="h4 mt-2 mb-0">{{ config('shop.name') }}</h1>
                <p class="text-muted small">请登录后进入系统</p>
            </div>

            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            <form method="post" action="{{ route('shop.login') }}" aria-label="登录表单">
                @csrf

                <div class="mb-3">
                    <label for="username" class="form-label">邮箱或用户名</label>
                    <input type="text"
                           id="username"
                           name="username"
                           class="form-control @error('username') is-invalid @enderror"
                           value="{{ old('username') }}"
                           placeholder="admin 或 admin@shop.demo"
                           autocomplete="username"
                           autocapitalize="none"
                           spellcheck="false"
                           required autofocus>
                    @error('username')
                        <div class="invalid-feedback" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">密码</label>
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           autocomplete="current-password"
                           required>
                    @error('password')
                        <div class="invalid-feedback" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-right-to-bracket me-1"></i>登录
                </button>
            </form>
        </section>
    </main>
</body>
</html>

