<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 · 页面不存在</title>
    <link rel="stylesheet" href="{{ asset('assets/pm/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/pm/css/font-awesome.min.css') }}"></head>
<body class="bg-light">
<div class="container" style="max-width: 640px; margin-top: 10vh;">
    <div class="card shadow-sm">
        <div class="card-body p-4 text-center">
            <i class="fa-solid fa-map text-secondary" style="font-size: 2.2rem;"></i>
            <h1 class="display-6 fw-bold mt-2 mb-1">404</h1>
            <h5 class="mb-3">页面不存在</h5>
            <p class="text-muted mb-4">这个地址在「演示研发科技 · 研发项目管理」里没有对应页面。可能是项目已经归档，或旧链接失效。</p>
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