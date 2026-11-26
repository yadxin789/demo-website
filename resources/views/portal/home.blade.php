<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>产品演示中心 · 五套独立系统</title>
    <meta name="description" content="一个演示站点，五套完全独立的企业管理系统：制造 ERP、研发项目管理、百货零售 POS、车间 MES、人事 HR。独立账号、独立数据库、独立界面。">
    <link rel="stylesheet" href="{{ asset('assets/portal/portal.css') }}">
</head>
<body>
    <header class="p-header">
        <div class="p-wrap p-header-inner">
            <div class="p-brand">
                <span class="p-mark">D</span>
                <span class="p-logo">产品演示中心</span>
            </div>
            <span class="p-header-note">独立账号 · 独立数据库 · 互不干扰</span>
        </div>
    </header>

    <main class="p-wrap p-main">
        <section class="p-hero">
            <span class="p-eyebrow">DEMO SUITE</span>
            <h1>一个站点，<em>{{ count($products) }}</em> 套独立系统</h1>
            <p>
                每张卡片背后都是一套完整的企业系统：自己的登录入口、自己的数据库、自己的界面风格。
                点进卡片先看产品介绍，再决定用哪个身份登进去试。
            </p>
            <div class="p-stats">
                <div><b>{{ count($products) }}</b><span>在线产品</span></div>
                <div><b>{{ 5 - count($products) }}</b><span>预留席位</span></div>
                <div><b>123456</b><span>统一演示密码</span></div>
            </div>
        </section>

        <div class="p-grid">
            @foreach ($products as $code => $p)
                <a class="p-card" href="{{ route($code . '.preview') }}" style="--c: {{ $p['theme']['primary'] }}">
                    <div class="p-card-top">
                        <span class="p-mark p-mark-sm">{{ $p['short'] }}</span>
                        <span class="p-chip">{{ $p['company'] }}</span>
                    </div>

                    <h2>{{ $p['label'] }}</h2>
                    <p class="p-tagline">{{ $p['tagline'] }}</p>

                    <ul class="p-feats">
                        @foreach (array_slice($p['features'], 0, 4) as $f)
                            <li>{{ \Illuminate\Support\Str::before($f, '（') }}</li>
                        @endforeach
                    </ul>

                    <div class="p-card-foot">
                        <span>查看产品介绍</span>
                        <span class="p-arrow">→</span>
                    </div>
                </a>
            @endforeach
        </div>

        <p class="p-footnote">
            演示账号密码统一为 <b>123456</b>，五个系统的账号互不通用 —— 这正是它们各自独立的地方。
            站内数据全部为虚构演示数据，不涉及任何真实客户信息。
        </p>
    </main>

    <footer class="p-footer">
        <div class="p-wrap p-footer-inner">
            <span>演示环境 · 功能展示用途</span>
            <span>数据均为虚构</span>
        </div>
    </footer>
</body>
</html>
