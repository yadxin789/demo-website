<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 · 系统开了小差 · 智能制造 MES</title>
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/fontawesome/css/all.min.css') }}">
</head>
<body style="background:#0f172a; min-height:100vh;">
<div class="container" style="max-width: 640px; margin-top: 12vh;">
    <div class="card border-0 shadow" style="border-radius:14px; overflow:hidden;">
        <div style="height:6px; background:linear-gradient(90deg,#f59e0b,#fbbf24);"></div>
        <div class="card-body p-5 text-center">
            <i class="fa-solid fa-screwdriver-wrench" style="font-size:2.2rem; color:#f59e0b;"></i>
            <h1 class="display-6 fw-bold mt-3 mb-1">500</h1>
            <h5 class="mb-3">产线还在转，页面先停了一下</h5>
            <p class="text-muted mb-4">服务端出了个意外，已经记进日志。稍后重试，或回工作台继续演示。</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ url('mes/dashboard') }}" class="btn text-white" style="background:#f59e0b;">回工作台</a>
                <a href="{{ url('/') }}" class="btn btn-outline-secondary">看看其他产品</a>
            </div>
        </div>
    </div>
    <p class="text-center small mt-3" style="color:#64748b;">演示环境 · 数据均为虚构</p>
</div>
</body>
</html>
