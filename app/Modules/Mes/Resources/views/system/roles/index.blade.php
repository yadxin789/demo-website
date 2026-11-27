@extends('mes::layouts.app')

@section('title', '系统 · 角色权限')
@section('sub', '五个岗位 × 14 条权限，勾选即授权，保存后立即生效')

@push('styles')
    <style>
        .perm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 14px; }
        .perm-item { display: flex; align-items: flex-start; gap: 7px; font-size: 13px; line-height: 1.35; }
        .perm-item input { margin-top: 2px; }
        .perm-item span b { font-weight: 600; }
        .perm-item span i { display: block; font-style: normal; font-size: 11px; color: #8a97a8; font-family: ui-monospace, Consolas, monospace; }
        .role-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
        .role-head .l { font-size: 15px; font-weight: 600; }
        .role-head .r { font-size: 12px; color: #8a97a8; }
    </style>
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success py-2" style="font-size:13px">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2" style="font-size:13px">{{ $errors->first() }}</div>
    @endif

    <div class="mes-card" style="margin-bottom:14px">
        <div style="font-size:13px; color:#4a5568; line-height:1.7">
            角色权限按岗位划分：<b>计划员</b>看主数据与工单下达，<b>班组长</b>拿派工、报工、领料，<b>质检员</b>做检验判定与不良处置，
            <b>设备工程师</b>管点检报修，<b>系统管理员</b>固定拥有全部权限（不允许收窄，防止演示时把自己锁在门外）。
            勾掉某项保存后，对应角色的账号刷新页面即失去该菜单能力。
        </div>
    </div>

    <div class="wo-grid" style="align-items:start">
        @foreach ($roles as $role)
            @php
                $granted = $role->permissions->pluck('name')->all();
                $isAdmin = $role->name === 'admin';
                $count = $holders[$role->id] ?? 0;
            @endphp
            <div class="mes-card">
                <div class="role-head">
                    <div class="l">{{ $roleLabels[$role->name] ?? $role->name }}
                        <span class="mes-badge b-released" style="margin-left:6px; font-weight:400">{{ $role->name }}</span>
                    </div>
                    <div class="r">{{ $count }} 个账号 · {{ $isAdmin ? '全部权限' : count($granted) . ' 条权限' }}</div>
                </div>

                <form method="post" action="{{ route('mes.system.roles.update', $role->name) }}">
                    @csrf
                    @method('PUT')
                    <div class="perm-grid">
                        @foreach ($permissions as $name => $label)
                            <label class="perm-item">
                                <input type="checkbox" name="permissions[]" value="{{ $name }}"
                                       @checked(in_array($name, $granted, true)) @disabled($isAdmin)>
                                <span><b>{{ $label }}</b><i>{{ $name }}</i></span>
                            </label>
                        @endforeach
                    </div>
                    <div style="display:flex; justify-content:flex-end; margin-top:12px">
                        <button class="btn btn-sm btn-primary" @disabled($isAdmin)>保存本角色权限</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
@endsection
