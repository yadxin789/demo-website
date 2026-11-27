<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>页面不存在</title>
    <link rel="stylesheet" href="{{ asset('assets/portal/portal.css') }}">
    {{-- 移动端适配（S19）：抽屉式侧栏 + 表格横向滚动 --}}
    <link rel="stylesheet" href="{{ asset('assets/shared/css/responsive.css') }}">
    <script defer src="{{ asset('assets/shared/js/responsive.js') }}"></script>
</head>
<body>
    <main class="p-wrap p-main e-404">
        <span class="e-404-code">404</span>
        <h1>这个页面不存在</h1>
        <p>可能链接已经过期，或者这套产品还没有开放演示。</p>
        <div class="e-404-actions">
            <a class="pv-btn" href="{{ route('portal.home') }}">返回产品列表</a>
            <a class="pv-btn pv-btn-light" href="{{ route('portal.about') }}">关于与合规</a>
        </div>
    </main>
</body>
</html>
