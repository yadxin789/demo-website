@extends('system.layout')

@section('title', '系统设置')
@section('sub', '产品配置与环境参数（只读，改动走配置文件与 .env）')

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">产品配置</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        <tr><th style="width:140px;">产品标识</th><td class="font-monospace small">{{ $product }}</td></tr>
                        <tr><th>产品名称</th><td>{{ $productCfg['name'] ?? $cfg['label'] }}</td></tr>
                        <tr><th>企业名（演示）</th><td>{{ $productCfg['company'] ?? '—' }}</td></tr>
                        <tr><th>描述</th><td class="small text-muted">{{ $productCfg['tagline'] ?? ($productCfg['description'] ?? '—') }}</td></tr>
                        <tr><th>品牌色</th><td><span class="badge" style="background:{{ $themeColor }};">{{ $themeColor }}</span></td></tr>
                        <tr><th>启用状态</th><td>{{ ($productCfg['enabled'] ?? true) ? '已启用' : '已关闭' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">环境参数</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @foreach ($env as $k => $v)
                            <tr><th class="font-monospace small" style="width:170px;">{{ $k }}</th><td class="font-monospace small text-muted">{{ $v ?: '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body small text-muted mb-0">
        演示站的设置以只读方式呈现，避免后台误改影响线上演示。需要调整品牌色、产品名称或企业名时，修改 <span class="font-monospace">config/products.php</span>；
        需要调整数据库、缓存、邮件等运行参数时，修改服务器上的 <span class="font-monospace">.env</span>，随后执行配置缓存重建。
    </div>
</div>
@endsection
