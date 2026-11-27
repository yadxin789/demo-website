<!doctype html>
<html lang="zh-CN">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/portal/favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>产品演示中心 · 企业管理系统演示</title>
    <meta name="description" content="五套企业管理系统演示：制造 ERP、研发项目管理、百货零售 POS、车间 MES、人事 HR。">
    <link rel="stylesheet" href="{{ asset('assets/portal/portal.css') }}">
    {{-- 移动端适配（S19）：抽屉式侧栏 + 表格横向滚动 --}}
    <link rel="stylesheet" href="{{ asset('assets/shared/css/responsive.css') }}">
    <script defer src="{{ asset('assets/shared/js/responsive.js') }}"></script>
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
            <div class="p-stats">
                <div><b>{{ count($products) }}</b><span>在线产品</span></div>
                <div><b>{{ 5 - count($products) }}</b><span>预留席位</span></div>
                <div><b>123456</b><span>统一演示密码</span></div>
            </div>
        </section>

        <div class="p-grid">
            @foreach ($products as $code => $p)
                <a class="p-card" href="{{ route($code . '.root') }}" style="--c: {{ $p['theme']['primary'] }}">
                    <div class="p-card-top">
                        <span class="p-mark p-mark-sm">{{ $p['short'] }}</span>
                        <span class="p-chip">{{ $p['company'] }}</span>
                    </div>

                    <h2>{{ $p['label'] }}</h2>
                    <p class="p-tagline">{{ $p['tagline'] }}</p>
                    @if (! empty($p['suited_short']))
                        <p class="p-suit"><span>适合</span>{{ $p['suited_short'] }}</p>
                    @endif

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

        @if (session('lead_status'))
            <div class="p-lead-ok" role="status">{{ session('lead_status') }}</div>
        @endif

        <section class="p-contact" id="contact">
            <div class="p-contact-copy">
                <h2>想先看哪一套？</h2>
                <p>
                    告诉我你现在的状况：是完全没上过系统，还是已经有 ERP 想补车间或人事。
                    我按你的情况直接把最合适的那一套演示环境开给你，不用自己一个个试。
                </p>
                <ul class="p-contact-list">
                    @if (config('portal.contact.email'))
                        <li><span>邮箱</span>{{ config('portal.contact.email') }}</li>
                    @endif
                    @if (config('portal.contact.wechat'))
                        <li><span>微信</span>{{ config('portal.contact.wechat') }}</li>
                    @endif
                    @if (config('portal.contact.phone'))
                        <li><span>电话</span>{{ config('portal.contact.phone') }}</li>
                    @endif
                    <li><span>时间</span>{{ config('portal.contact.hours') }}</li>
                </ul>
            </div>

            <form class="p-lead-form" method="post" action="{{ route('portal.lead.store') }}" novalidate>
                @csrf
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                       aria-hidden="true" style="position:absolute; left:-9999px; height:0; width:0; opacity:0;">

                @if ($errors->any())
                    <div class="p-lead-err" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="p-lead-grid">
                    <label>
                        <span>怎么称呼你</span>
                        <input type="text" name="name" maxlength="40" required
                               value="{{ old('name') }}" placeholder="例如：王工">
                    </label>
                    <label>
                        <span>联系方式</span>
                        <input type="text" name="contact" maxlength="60" required
                               value="{{ old('contact') }}" placeholder="手机 / 微信 / 邮箱">
                    </label>
                    <label>
                        <span>公司（可选）</span>
                        <input type="text" name="company" maxlength="80"
                               value="{{ old('company') }}" placeholder="例如：某某精密制造">
                    </label>
                    <label>
                        <span>感兴趣的产品</span>
                        <select name="interest">
                            <option value="all">还不确定，帮我判断</option>
                            @foreach ($products as $code => $p)
                                <option value="{{ $code }}" @selected(old('interest') === $code)>{{ $p['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <label class="p-lead-wide">
                    <span>想了解的问题（可选）</span>
                    <textarea name="message" rows="3" maxlength="500"
                              placeholder="例如：我们已经有 ERP，只想管车间。">{{ old('message') }}</textarea>
                </label>

                <button type="submit">预约演示</button>
                <p class="p-lead-tip">
                    只用于回你一次演示安排，不会进任何产品库，也不会发给第三方。
                </p>
            </form>
        </section>
    </main>

    <footer class="p-footer">
        <div class="p-wrap p-footer-inner">
            <span>演示环境 · 功能展示用途</span>
            <span>
                数据均为虚构 ·
                <a href="{{ route('portal.about') }}">关于与合规</a>
                @if (config('portal.icp')) · {{ config('portal.icp') }}@endif
            </span>
        </div>
    </footer>
</body>
</html>

