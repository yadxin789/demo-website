<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>产品演示中心</title>
    <link rel="stylesheet" href="{{ asset('assets/portal/portal.css') }}">
</head>
<body>
    <header class="p-header">
        <div class="p-wrap p-header-inner">
            <span class="p-logo">产品演示中心</span>
            <span class="p-sub">Demo Suite</span>
        </div>
    </header>

    <main class="p-wrap p-main">
        <div class="p-hero">
            <h1>五个独立产品，一套演示环境</h1>
            <p>每张卡片都是一个独立系统：独立账号、独立数据库、独立界面。点进去先看产品介绍，再决定是否登录试用。</p>
        </div>

        <div class="p-grid">
            @foreach ($products as $code => $p)
                <a class="p-card" href="{{ route($code . '.preview') }}" style="--c: {{ $p['theme']['primary'] }}">
                    <div class="p-card-top">
                        <span class="p-badge">{{ $p['short'] }}</span>
                        <span class="p-arrow">进入 →</span>
                    </div>
                    <h2>{{ $p['label'] }}</h2>
                    <p class="p-tagline">{{ $p['tagline'] }}</p>
                    <div class="p-meta">{{ $p['company'] }}</div>
                </a>
            @endforeach
        </div>

        <p class="p-footnote">
            当前在线 {{ count($products) }} 个产品。演示账号密码统一为 123456，五个系统的账号互不通用。
        </p>
    </main>

    <footer class="p-footer">
        <div class="p-wrap">演示环境 · 仅为产品功能展示，数据均为虚构</div>
    </footer>
</body>
</html>
