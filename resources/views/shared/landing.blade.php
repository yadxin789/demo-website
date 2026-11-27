{{-- 五产品对外首页骨架（S8）：免登录可看，只读 config，不查任何产品库（HC-41）
     用法：@include('shared::landing', ['product' => config('products.products.mes'), 'meta' => [...]]) --}}
@php
    $code = $product['code'];
    $theme = $product['theme'] ?? ['primary' => '#0d6efd', 'accent' => '#0b5ed7'];
    $contact = config('portal.contact');
    $account = $product['demo_account'] ?? ['email' => 'admin', 'password' => '123456'];
    $features = array_slice($product['features'] ?? [], 0, 8);
    $stats = $meta['stats'] ?? [];
    $fitYes = $meta['fit_yes'] ?? [];
    $fitNo = $meta['fit_no'] ?? [];
@endphp
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $product['label'] }} · {{ $product['company'] }}</title>
    <meta name="description" content="{{ $product['tagline'] }}。{{ \Illuminate\Support\Str::limit($product['description'], 110) }}">
    <link rel="stylesheet" href="{{ asset('assets/shared/css/landing.css') }}">
</head>
<body style="--c: {{ $theme['primary'] }}; --c-dark: {{ $theme['accent'] }};">

<header class="l-header">
    <div class="l-wrap l-header-inner">
        <div class="l-brand">
            <span class="l-mark">{{ $product['short'] }}</span>
            <span>{{ $product['label'] }}</span>
        </div>
        <nav class="l-header-nav">
            <a href="{{ route('portal.home') }}">← 全部产品</a>
            <a class="l-cta" href="{{ route($code . '.login') }}">进入演示登录</a>
        </nav>
    </div>
</header>

<main>
    <section class="l-hero">
        <div class="l-wrap">
            <span class="l-eyebrow">{{ $product['company'] }}</span>
            <h1>{{ $meta['headline'] ?? $product['label'] }}</h1>
            <p class="l-lead">{{ $meta['intro'] ?? $product['description'] }}</p>
            <div class="l-hero-actions">
                <a class="l-btn l-btn-primary" href="{{ route($code . '.login') }}">进入演示登录 →</a>
                <a class="l-btn l-btn-ghost" href="#capability">看看能做什么</a>
            </div>

            @if ($stats)
                <div class="l-stats">
                    @foreach ($stats as $s)
                        <div><b>{{ $s['n'] }}</b><span>{{ $s['t'] }}</span></div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="l-section" id="capability">
        <div class="l-wrap">
            <h2>系统能力</h2>
            <p class="l-section-sub">{{ $product['tagline'] }}</p>
            <div class="l-grid">
                @foreach ($features as $i => $f)
                    <div class="l-item {{ $i % 2 ? 'l-item-alt' : '' }}">
                        <span class="l-item-no">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <p>{{ $f }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="l-section l-section-alt">
        <div class="l-wrap">
            <h2>适合谁用</h2>
            <p class="l-section-sub">{{ $product['suited'] }}</p>
            <div class="l-fit">
                <div class="l-fit-card l-fit-yes">
                    <h3>推荐这套系统</h3>
                    <ul>
                        @foreach ($fitYes as $y)
                            <li>{{ $y }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="l-fit-card l-fit-no">
                    <h3>建议看其它产品</h3>
                    <ul>
                        @foreach ($fitNo as $n)
                            <li>{{ $n }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="l-account">
                <div class="l-account-head">演示账号（直接登录，不用注册）</div>
                <div class="l-account-body">
                    <dl>
                        <div><dt>账号</dt><dd>{{ $account['email'] }}</dd></div>
                        <div><dt>密码</dt><dd>{{ $account['password'] }}</dd></div>
                    </dl>
                    <span class="l-account-note">数据均为虚构，每天重置</span>
                    <a class="l-btn l-btn-primary" href="{{ route($code . '.login') }}">登录这套系统</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="l-footer">
    <div class="l-wrap l-footer-inner">
        <span>{{ $product['company'] }} · 演示环境</span>
        <span class="l-footer-meta">
            <a href="{{ route('portal.home') }}">← 返回产品列表</a>
            @if (! empty($contact['email']))
                <span>邮箱 {{ $contact['email'] }}</span>
            @endif
            @if (! empty($contact['wechat']))
                <span>微信 {{ $contact['wechat'] }}</span>
            @endif
            <span>{{ config('portal.icp') }}</span>
        </span>
    </div>
</footer>

</body>
</html>
