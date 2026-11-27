@extends('system.layout')

@section('title', '角色管理')
@section('sub', '角色清单、成员数与权限点数量')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>角色（{{ count($roles) }}）</span>
        <span class="small text-muted">角色决定前台可见范围，本页只做核对，不做业务授权变更</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th style="width:60px;">ID</th><th>角色名称</th><th>标识</th><th>守卫</th><th class="text-end">成员</th><th class="text-end">权限点</th></tr></thead>
            <tbody>
                @forelse ($roles as $r)
                    <tr>
                        <td class="text-muted small">{{ $r['id'] }}</td>
                        <td>{{ $r['name'] }}</td>
                        <td class="font-monospace small text-muted">{{ $r['code'] }}</td>
                        <td class="small text-muted">{{ $r['guard'] ?: '—' }}</td>
                        <td class="text-end">{{ $r['users'] }}</td>
                        <td class="text-end">{{ $r['permissions'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">暂无角色</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">角色与岗位的关系</div>
            <div class="card-body small text-muted mb-0">
                岗位描述「人在哪个位置」，角色描述「能看哪些数据」。演示站沿用原项目口径：角色在登录时绑定，后台不提供跨角色授权，避免误操作影响前台演示。
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">权限点维护</div>
            <div class="card-body small text-muted mb-0">
                @if (! empty($cfg['extras']))
                    权限点与授权矩阵在各产品的「扩展维护」页面维护：
                    @foreach ($cfg['extras'] as $x)
                        <a class="d-inline-block me-3" href="{{ $x['url'] }}">{{ $x['label'] }}</a>
                    @endforeach
                @else
                    本产品沿用角色级的粗粒度权限，不单独维护权限点。
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
