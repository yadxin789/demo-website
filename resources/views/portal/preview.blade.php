<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $p['label'] }} · 产品介绍</title>
    <link rel="stylesheet" href="{{ asset('assets/portal/portal.css') }}">
</head>
<body class="pv-body" style="--c: {{ $p['theme']['primary'] }}">
    <header class="p-header">
        <div class="p-wrap p-header-inner">
            <a class="p-back" href="{{ route('portal.home') }}">← 返回产品列表</a>
            <span class="p-sub">{{ $p['short'] }}</span>
        </div>
    </header>

    <main class="p-wrap p-main">
        <div class="pv-head">
            <div class="pv-badge">{{ $p['short'] }}</div>
            <div>
                <h1>{{ $p['label'] }}</h1>
                <p class="pv-tagline">{{ $p['tagline'] }}</p>
                <p class="pv-company">{{ $p['company'] }}</p>
            </div>
        </div>

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
                <p class="pv-desc">功能说明整理中。</p>
            @endif
        </section>

        <section class="pv-section">
            <h3>演示账号</h3>
            <div class="pv-account">
                <div><span>账号</span><b>{{ $p['demo_account']['email'] }}</b></div>
                <div><span>密码</span><b>123456</b></div>
            </div>
        </section>

        <div class="pv-actions">
            <a class="pv-btn" href="{{ route($p['code'] . '.login') }}">进入系统登录 →</a>
            <a class="pv-btn pv-btn-ghost" href="{{ route('portal.home') }}">看看其他产品</a>
        </div>
    </main>

    <footer class="p-footer">
        <div class="p-wrap">演示环境 · {{ $p['company'] }}</div>
    </footer>
</body>
</html>
