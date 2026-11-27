<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · 没有权限 · 人事管理 HR</title>
    <link rel="stylesheet" href="{{ asset('assets/hr/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/hr/vendor/fontawesome/css/all.min.css') }}">
</head>
<body style="background:#241a3d; min-height:100vh;">
<div class="container" style="max-width: 640px; margin-top: 12vh;">
    <div class="card border-0 shadow" style="border-radius:14px; overflow:hidden;">
        <div style="height:6px; background:linear-gradient(90deg,#d6336c,#f06595);"></div>
        <div class="card-body p-5 text-center">
            <i class="fa-solid fa-user-lock" style="font-size:2.2rem; color:#d6336c;"></i>
            <h1 class="display-6 fw-bold mt-3 mb-1">403</h1>
            <h5 class="mb-3">这个页面不在你的权限范围</h5>
            <p class="text-muted mb-4">
                HR 按岗位分权：HRBP 管组织与时薪，专员管考勤与招聘，主管只看本部门并做审批，员工只能看自己的工资条与请假。
            </p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ url('hr/dashboard') }}" class="btn text-white" style="background:#5f3dc4;">回人力看板</a>
                <a href="{{ url('hr/logout') }}" class="btn btn-outline-secondary">换个账号登录</a>
            </div>
        </div>
    </div>
    <p class="text-center small mt-3" style="color:#9a94b0;">演示环境 · 用 admin / manager / specialist / dept / staff 切换五个岗位</p>
</div>
</body>
</html>
