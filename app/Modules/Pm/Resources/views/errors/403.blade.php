<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · 该页面不属于您的角色</title>
    <link rel="stylesheet" href="{{ asset('assets/pm/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/pm/css/font-awesome.min.css') }}"></head>
<body class="bg-light">
<div class="container" style="max-width: 640px; margin-top: 10vh;">
    <div class="card shadow-sm">
        <div class="card-body p-4 text-center">
            <i class="fa-solid fa-lock text-secondary" style="font-size: 2.2rem;"></i>
            <h1 class="display-6 fw-bold mt-2 mb-1">403</h1>
            <h5 class="mb-3">该页面不属于您的角色</h5>
            <p class="text-muted mb-4">当前账号没有访问这个页面的权限。演示环境里不同角色能看到的菜单是不同的，换一个账号或请管理员调整权限后再试。</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ url('pm/dashboard') }}" class="btn btn-primary">返回工作台</a>
                <a href="{{ route('portal.home') }}" class="btn btn-outline-secondary">看看其他产品</a>
            </div>
        </div>
    </div>
    <p class="text-center text-muted small mt-3">演示环境 · 数据均为虚构</p>
</div>
</body>
</html>