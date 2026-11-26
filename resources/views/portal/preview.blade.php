<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $p['label'] }} · 产品介绍</title>
    <meta name="description" content="{{ $p['short'] }} — {{ $p['tagline'] }}。独立登录入口、独立数据库、独立界面，演示账号密码 123456。">
    <link rel="stylesheet" href="{{ asset('assets/portal/portal.css') }}">
</head>
<body class="pv-body" style="--c: {{ $p['theme']['primary'] }}">
    <header class="p-header">
        <div class="p-wrap p-header-inner">
            <a class="p-brand" href="{{ route('portal.home') }}">
                <span class="p-mark">{{ $p['short'] }}</span>
                <span class="p-logo">{{ $p['label'] }}</span>
            </a>
            <span class="p-header-note">{{ $p['company'] }}</span>
        </div>
    </header>

    <main class="p-wrap p-main">
        <section class="pv-hero">
            <span class="pv-badge">{{ $p['short'] }}</span>
            <h1>{{ $p['label'] }}</h1>
            <p class="pv-tagline">{{ $p['tagline'] }}</p>
            <p class="pv-company">{{ $p['company'] }} · 演示环境（虚构数据）</p>
        </section>

        @if (! empty($p['description']))
            <section class="pv-section">
                <h3>这是什么</h3>
                <p class="pv-desc">{{ $p['description'] }}</p>
            </section>
        @endif

        <section class="pv-section">
            <h3>主要功能</h3>
            @if (! empty($p['features']))
                <ul class="pv-features">
                    @foreach ($p['features'] as $f)
                        <li>{{ $f }}</li>
                    @endforeach
                </ul>
            @else
                <p class="pv-desc">功能清单整理中。</p>
            @endif
        </section>

        <section class="pv-section">
            <h3>演示账号</h3>
            <div class="pv-account">
                <div><span>账号</span><b>{{ $p['demo_account']['email'] ?? 'admin' }}</b></div>
                <div><span>密码</span><b>123456</b></div>
            </div>
            <p class="pv-desc" style="margin-top:12px">
                这个账号只能登进{{ $p['label'] }}。五套系统各自独立的用户表与登录校验，
                同一个浏览器里换个产品就要重新登录 —— 账号不通用是设计，不是缺陷。
            </p>
        </section>

        <div class="pv-actions">
            <a class="pv-btn" href="{{ route($p['code'] . '.login') }}">进入{{ $p['short'] }}登录 →</a>
            <a class="pv-btn pv-btn-ghost" href="{{ route('portal.home') }}">看看其他产品</a>
        </div>
    </main>

    <footer class="p-footer">
        <div class="p-wrap p-footer-inner">
            <span>演示环境 · {{ $p['company'] }}</span>
            <span>数据均为虚构 · 不含任何真实客户信息</span>
        </div>
    </footer>
</body>
</html>
