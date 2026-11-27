<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 · 系统开了小差 · 人事管理 HR</title>
    <link rel="stylesheet" href="{{ asset('assets/hr/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/hr/vendor/fontawesome/css/all.min.css') }}">
</head>
<body style="background:#241a3d; min-height:100vh;">
<div class="container" style="max-width: 640px; margin-top: 12vh;">
    <div class="card border-0 shadow" style="border-radius:14px; overflow:hidden;">
        <div style="height:6px; background:linear-gradient(90deg,#5f3dc4,#9b7ff0);"></div>
        <div class="card-body p-5 text-center">
            <i class="fa-solid fa-plug-circle-xmark" style="font-size:2.2rem; color:#7c5ce2;"></i>
            <h1 class="display-6 fw-bold mt-3 mb-1">500</h1>
            <h5 class="mb-3">服务端出了点状况</h5>
            <p class="text-muted mb-4">这一页没有算出来，已经记进日志。稍后重试，或回人力看板继续演示。</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ url('hr/dashboard') }}" class="btn text-white" style="background:#5f3dc4;">回人力看板</a>
                <a href="{{ url('/') }}" class="btn btn-outline-secondary">看看其他产品</a>
            </div>
        </div>
    </div>
    <p class="text-center small mt-3" style="color:#9a94b0;">演示环境 · 数据均为虚构</p>
</div>
</body>
</html>
