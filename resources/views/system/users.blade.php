@extends('system.layout')

@section('title', '用户管理')
@section('sub', '账号、部门岗位归属与启用状态')

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>账号列表（{{ count($users) }}）</span>
        <form class="d-flex gap-2" method="GET" action="{{ $consoleBase }}/users">
            <input type="search" name="q" value="{{ $keyword }}" class="form-control form-control-sm" placeholder="姓名 / 账号 / 邮箱" style="width:220px;">
            <button class="btn btn-sm btn-outline-secondary">搜索</button>
            @if ($keyword !== '')
                <a class="btn btn-sm btn-link" href="{{ $consoleBase }}/users">清除</a>
            @endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>姓名</th>
                    <th>登录账号</th>
                    <th>邮箱</th>
                    <th>部门</th>
                    <th>岗位</th>
                    <th>角色</th>
                    <th>最近登录</th>
                    <th class="text-end" style="width:110px;">状态</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td class="text-muted small">{{ $u['id'] }}</td>
                        <td>{{ $u['name'] ?: '—' }}</td>
                        <td class="font-monospace small">{{ $u['account'] ?: '—' }}</td>
                        <td class="small text-muted">{{ $u['email'] ?: '—' }}</td>
                        <td class="small">{{ $u['department'] }}</td>
                        <td class="small">{{ $u['position'] }}</td>
                        <td class="small">{{ $u['role'] }}</td>
                        <td class="small text-muted">{{ $u['last_login'] ?: '未登录' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ $consoleBase }}/users/{{ $u['id'] }}/status">
                                @csrf
                                <button class="btn btn-sm {{ $u['active'] ? 'btn-outline-danger' : 'btn-outline-success' }}" title="切换启用状态">
                                    {{ $u['active'] ? '停用' : '启用' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">没有匹配的账号</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        账号的新增与密码重置沿用各产品原有的登录体系；本页只做启用/停用与归属核对，避免在后台产生业务动作。
    </div>
</div>
@endsection
