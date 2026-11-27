@extends('system.layout')

@section('title', '账号管理')
@section('sub', '账号的新建、编辑、角色与启停；角色按 guard「hr」隔离')

@section('content')
    <div class="hr-card">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h3 style="margin:0">账号 {{ $rows->count() }} 个</h3>
            <div class="d-flex gap-2">
                <form class="d-flex gap-2" method="get" action="{{ route('hr.system.users.index') }}" style="margin:0">
                    <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="搜姓名/用户名/邮箱/工号" style="width:200px">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">搜</button>
                </form>
                <a class="btn btn-sm btn-primary" href="{{ route('hr.system.users.create') }}">+ 新增账号</a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success py-2" style="font-size:13px">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-warning py-2" style="font-size:13px">{{ session('error') }}</div>
        @endif

        <table class="hr-table">
            <thead>
            <tr><th>姓名</th><th>用户名</th><th>邮箱</th><th>工号</th><th>角色</th><th>状态</th><th>最近登录</th><th>改角色</th><th>启停</th><th>操作</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $u)
                <tr>
                    <td><span class="hr-avatar">{{ mb_substr($u->name, 0, 1) }}</span> <b>{{ $u->name }}</b></td>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $u->username ?? '—' }}</td>
                    <td style="font-size:12px">{{ $u->email }}</td>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $u->employee_no ?? '—' }}</td>
                    <td>
                        <span class="hr-badge {{ $u->role_code === 'admin' ? 'b-done' : 'b-progress' }}">
                            {{ $roleText[$u->role_code] ?? $u->role_code }}
                        </span>
                    </td>
                    <td>
                        <span class="hr-badge {{ ($u->status ?? 'active') === 'active' ? 'b-done' : 'b-bad' }}">
                            {{ ($u->status ?? 'active') === 'active' ? '正常' : '停用' }}
                        </span>
                    </td>
                    <td style="font-size:12px">{{ $u->last_login_at?->format('Y-m-d H:i') ?? '从未登录' }}</td>
                    <td>
                        <form class="d-flex gap-1" method="post" action="{{ route('hr.system.users.role', $u->id) }}" style="margin:0">
                            @csrf
                            <select class="form-select form-select-sm" name="role" style="width:120px">
                                @foreach ($roles as $r)
                                    <option value="{{ $r }}" @selected($u->role_code === $r)>{{ $roleText[$r] }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-primary" type="submit">改</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="{{ route('hr.system.users.toggle', $u->id) }}" style="margin:0">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                {{ ($u->status ?? 'active') === 'active' ? '停用' : '启用' }}
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.system.users.edit', $u->id) }}">编辑</a>
                            <form method="post" action="{{ route('hr.system.users.destroy', $u->id) }}" style="margin:0" onsubmit="return confirm('删除账号 {{ $u->name }}？')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="hr-empty">没有匹配的账号。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="font-size:12px; color:#9a9aad; margin-top:10px">
            新增账号的密码由操作者填写，页面不预填任何口令；演示账号的统一口令只出现在产品首页的演示账号卡上。
        </div>
    </div>
@endsection
