<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · 没有权限 · 智能制造 MES</title>
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/mes/vendor/fontawesome/css/all.min.css') }}">
</head>
<body style="background:#0f172a; min-height:100vh;">
<div class="container" style="max-width: 640px; margin-top: 12vh;">
    <div class="card border-0 shadow" style="border-radius:14px; overflow:hidden;">
        <div style="height:6px; background:linear-gradient(90deg,#dc2626,#f97316);"></div>
        <div class="card-body p-5 text-center">
            <i class="fa-solid fa-lock" style="font-size:2.2rem; color:#dc2626;"></i>
            <h1 class="display-6 fw-bold mt-3 mb-1">403</h1>
            <h5 class="mb-3">当前岗位没有这个权限</h5>
            <p class="text-muted mb-4">
                这道工序不在你的职责范围内。MES 按岗位分权：操作工看任务与报工，质检员看检验，设备员看设备，计划员管排产，管理员管全部。
            </p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ url('mes/dashboard') }}" class="btn text-white" style="background:#f59e0b;">回我的工作台</a>
                <a href="{{ url('mes/logout') }}" class="btn btn-outline-secondary">换个账号登录</a>
            </div>
        </div>
    </div>
    <p class="text-center small mt-3" style="color:#64748b;">演示环境 · 用 admin / plan / lead / qc / eq 切换五个岗位</p>
</div>
</body>
</html>
