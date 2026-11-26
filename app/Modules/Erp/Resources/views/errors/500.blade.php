<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 · 系统繁忙</title>
    <link rel="stylesheet" href="{{ asset('assets/erp/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/erp/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/erp/css/erp.css') }}"></head>
<body class="bg-light">
<div class="container" style="max-width: 640px; margin-top: 10vh;">
    <div class="card shadow-sm">
        <div class="card-body p-4 text-center">
            <i class="fa-solid fa-triangle-exclamation text-secondary" style="font-size: 2.2rem;"></i>
            <h1 class="display-6 fw-bold mt-2 mb-1">500</h1>
            <h5 class="mb-3">系统繁忙</h5>
            <p class="text-muted mb-4">服务端处理这个请求时出错了。演示环境不会丢弃已录入的数据，请刷新重试，或回到工作台换一个功能。</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ url('erp/home') }}" class="btn btn-primary">返回工作台</a>
                <a href="{{ route('portal.home') }}" class="btn btn-outline-secondary">看看其他产品</a>
            </div>
        </div>
    </div>
    <p class="text-center text-muted small mt-3">演示环境 · 数据均为虚构</p>
</div>
</body>
</html>