<!doctype html>
<html lang="zh-CN">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/hr/favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登录 · 人事管理 HR</title>
    <link rel="stylesheet" href="{{ asset('assets/hr/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/hr/vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/hr/hr.css') }}">
</head>
<body>
<div class="hr-login-wrap">
    <div class="hr-login-card">
        <div class="hr-login-brand">
            <span class="mark">HR</span>
            <h1>人事管理 HR</h1>
        </div>
        <p class="hr-login-sub">从招人进来到发工资走人</p>

        @if (session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('hr.login.submit') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="account">邮箱或用户名</label>
                <input class="form-control" id="account" name="account" value="{{ old('account') }}" placeholder="admin 或 admin@hr.demo" autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">密码</label>
                <input class="form-control" id="password" name="password" type="password" value="">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                <label class="form-check-label" for="remember">记住我</label>
            </div>
            <button class="hr-login-btn" type="submit">登录</button>
        </form>

        <div class="hr-login-tip">
            演示账号：<b>admin@hr.demo</b>（管理员）<br>
            其它岗位：manager@hr.demo / specialist@hr.demo / dept@hr.demo / staff@hr.demo<br>
            统一密码 <b>123456</b>
        </div>

        <div class="hr-login-foot">
            <a href="{{ route('portal.home') }}" class="text-decoration-none">← 回产品列表</a>
        </div>
    </div>
</div>
</body>
</html>

