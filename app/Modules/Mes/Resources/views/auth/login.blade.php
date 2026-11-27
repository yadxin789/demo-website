<!doctype html>
<html lang="zh-CN">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/mes/favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登录 · 智能制造 MES</title>
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/mes/mes.css') }}">
</head>
<body class="mes-body">
<div class="mes-login-wrap">
    <div class="mes-login-card">
        <div class="mes-login-brand">
            <span class="mark">MES</span>
            <h1>精工精密制造 · MES</h1>
        </div>
        <p class="mes-login-sub">车间执行系统 · 苏州一号车间</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif
        @if (session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif

        <form method="post" action="{{ route('mes.login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">账号 / 邮箱</label>
                <input type="text" name="account" class="form-control" value="{{ old('account') }}" required autofocus autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label">密码</label>
                <input type="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button class="mes-login-btn" type="submit">进入车间</button>
        </form>

        <div class="mes-login-tip">
            演示账号 <b>admin</b> / 密码 <b>123456</b><br>
            这套 MES 与站点上的 ERP、项目管理、零售系统完全独立：独立的账号表、独立的数据库、独立的界面。
        </div>

        <div class="mes-login-foot">
            <a href="{{ route('portal.home') }}">← 返回产品列表</a>
            <span style="margin:0 8px">·</span>
            <a href="{{ route('mes.preview') }}">先看产品介绍</a>
        </div>
    </div>
</div>
</body>
</html>

