@extends('mes::layouts.app')

@section('title', '系统 · 账号')
@section('sub', '演示账号的岗位与启停。改角色即时生效，密码统一由 Seeder 维护')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">账号总数</div>
            <div class="num">{{ $stat['total'] }}</div>
            <div class="hint">个</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">启用中</div>
            <div class="num">{{ $stat['active'] }}</div>
            <div class="hint">可登录</div>
        </div>
        <div class="mes-kpi {{ $stat['disabled'] > 0 ? 'bad' : '' }}">
            <div class="lbl">已停用</div>
            <div class="num">{{ $stat['disabled'] }}</div>
            <div class="hint">登录被拦</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">角色</div>
            <div class="num">{{ count($roles) }}</div>
            <div class="hint">岗位</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
            <div style="font-weight:600">账号列表</div>
            <a class="btn btn-sm btn-primary" href="{{ route('mes.system.users.create') }}">+ 新增账号</a>
        </div>

        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px">
            <input name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:200px" placeholder="姓名 / 账号 / 邮箱 / 工号">
            <select name="status" class="form-select form-select-sm" style="width:130px">
                <option value="">全部状态</option>
                <option value="active" @selected($status === 'active')>启用</option>
                <option value="disabled" @selected($status === 'disabled')>停用</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary">查询</button>
            <span style="font-size:12px; color:#8a97a8; margin-left:4px">角色权限见「角色权限」页；新增账号的密码由操作者填写，页面不预填</span>
        </form>

        @if (session('status'))
            <div class="alert alert-success py-2" style="font-size:13px">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger py-2" style="font-size:13px">{{ $errors->first() }}</div>
        @endif

        <table class="mes-table" style="font-size:13px">
            <thead>
            <tr><th>工号</th><th>姓名</th><th>用户名</th><th>邮箱</th><th>部门 / 岗位</th><th>角色</th><th>状态</th><th style="width:300px">调整</th><th style="width:120px">操作</th></tr>
            </thead>
            <tbody>
            @forelse ($users as $u)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $u->employee_no ?: '—' }}</td>
                    <td>{{ $u->name }}</td>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $u->username ?: '—' }}</td>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px; color:#6b7a90">{{ $u->email }}</td>
                    <td style="color:#6b7a90">{{ $u->department?->name ?? ($u->team ?: '—') }}{{ $u->position ? ' / ' . $u->position->name : '' }}</td>
                    <td>
                        @foreach ($u->roles as $r)
                            <span class="mes-badge b-released">{{ $roles[$r->name] ?? $r->name }}</span>
                        @endforeach
                        @if ($u->roles->isEmpty())
                            <span class="mes-badge b-draft">未分配</span>
                        @endif
                    </td>
                    <td>
                        <span class="mes-badge {{ $u->status === 'active' ? 'b-done' : 'b-bad' }}">{{ $u->status === 'active' ? '启用' : '停用' }}</span>
                    </td>
                    <td>
                        <form method="post" action="{{ route('mes.system.users.update', $u) }}" style="display:flex; gap:6px; align-items:center">
                            @csrf
                            @method('PUT')
                            <select name="role" class="form-select form-select-sm" style="width:118px">
                                @foreach ($roles as $key => $label)
                                    <option value="{{ $key }}" @selected($u->roles->first()?->name === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="status" class="form-select form-select-sm" style="width:82px">
                                <option value="active" @selected($u->status === 'active')>启用</option>
                                <option value="disabled" @selected($u->status !== 'active')>停用</option>
                            </select>
                            <button class="btn btn-sm btn-outline-primary">保存</button>
                        </form>
                    </td>
                    <td>
                        <div style="display:flex; gap:6px">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.system.users.edit', $u) }}">编辑</a>
                            <form method="post" action="{{ route('mes.system.users.destroy', $u) }}" onsubmit="return confirm('删除账号 {{ $u->name }}？')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="mes-empty">没有匹配的账号。</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
