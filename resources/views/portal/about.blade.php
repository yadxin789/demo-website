<!doctype html>
<html lang="zh-CN">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/portal/favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>关于与合规 · {{ config('portal.name') }}</title>
    <meta name="description" content="本站是一个企业管理系统演示站：五套独立系统并列展示，全部数据为虚构。这里是站点主体信息、隐私说明与备案信息。">
    <link rel="stylesheet" href="{{ asset('assets/portal/portal.css') }}">
</head>
<body class="pv-body">
    <header class="p-header">
        <div class="p-wrap p-header-inner">
            <a class="p-brand" href="{{ route('portal.home') }}">
                <span class="p-mark">D</span>
                <span class="p-logo">{{ config('portal.name') }}</span>
            </a>
            <span class="p-header-note">关于本站 · 隐私与合规</span>
        </div>
    </header>

    <main class="p-wrap p-main">
        <section class="pv-hero">
            <span class="pv-badge">ABOUT</span>
            <h1>关于与合规</h1>
            <p class="pv-tagline">这个站点是什么、数据从哪来、你留下的信息怎么处理。</p>
        </section>

        <section class="pv-section">
            <h3>这是什么站</h3>
            <p class="pv-desc">
                这是一个企业管理系统演示站，用来让潜在客户在浏览器里直接看到成品长什么样、点得动哪些页面。
                站点上并列展示 {{ count($products) }} 套系统：
                @foreach ($products as $p)
                    {{ $p['label'] }}@if (! $loop->last) 、@endif
                @endforeach
                。每套系统都有自己的登录入口、自己的数据库、自己的界面风格，账号互不通用 —— 这是刻意的设计，不是缺陷。
            </p>
        </section>

        <section class="pv-section">
            <h3>数据说明</h3>
            <p class="pv-desc">
                站内所有业务数据（公司名、人名、订单、工单、工资、考勤记录等）全部是为演示虚构的，
                不对应任何真实企业或真实个人。演示账号的密码统一由初始化脚本写入，仅用于演示环境。
                请勿在演示环境里填写任何真实的客户资料、员工信息或财务数据。
            </p>
        </section>

        <section class="pv-section">
            <h3>隐私说明</h3>
            <p class="pv-desc">
                这个站点不主动采集访客的个人信息：不接入第三方统计、不投放广告、不做用户画像。
                只有一种情况会保存你的信息 —— 你在首页「预约演示」里主动提交的内容（称呼、联系方式、公司、问题）。
                这些内容只存在本站自己的线索库里，用于回你一次演示安排，不会用于营销群发，也不会提供给第三方。
                如需删除已提交的信息，发一封邮件到下方邮箱说明即可，我们会在三个工作日内清除。
            </p>
        </section>

        <section class="pv-section">
            <h3>联系方式与备案</h3>
            <ul class="pv-features">
                <li>邮箱：{{ config('portal.contact.email') }}</li>
                <li>电话：{{ config('portal.contact.phone') }}（{{ config('portal.contact.hours') }}）</li>
                <li>微信：{{ config('portal.contact.wechat') }}</li>
                <li>备案：{{ config('portal.icp') }}@if (config('portal.police')) · {{ config('portal.police') }}@endif</li>
                <li>信息更新日期：{{ config('portal.updated_at') }}</li>
            </ul>
        </section>

        <section class="pv-section">
            <h3>免责声明</h3>
            <p class="pv-desc">
                演示站里的功能与界面会持续调整，页面上的截图、菜单与数据不代表最终交付形态；
                具体功能范围、实施周期与报价以正式沟通后的方案为准。
            </p>
        </section>

        <div class="pv-actions">
            <a class="pv-btn" href="{{ route('portal.home') }}">← 回到产品列表</a>
            <a class="pv-btn pv-btn-ghost" href="{{ route('portal.home') }}#contact">去预约演示</a>
        </div>
    </main>

    <footer class="p-footer">
        <div class="p-wrap p-footer-inner">
            <span>{{ config('portal.copyright') ?: config('portal.name') }}</span>
            <span>{{ config('portal.icp') }}</span>
        </div>
    </footer>
</body>
</html>
