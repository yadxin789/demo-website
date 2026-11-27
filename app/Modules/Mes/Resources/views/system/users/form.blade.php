@extends('mes::layouts.app')

@php
    $isEdit = $mode === 'edit';
@endphp

@section('title', $isEdit ? '系统 · 编辑账号' : '系统 · 新增账号')
@section('sub', $isEdit ? '修改账号信息、部门岗位与角色；密码留空则保持不变' : '新建 MES 账号，密码由你现场填写')

@section('content')
    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px">
            <div style="font-weight:600">{{ $isEdit ? '编辑账号：' . $user->name : '新增账号' }}</div>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.system.users.index') }}">返回列表</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2" style="font-size:13px">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ $isEdit ? route('mes.system.users.profile', $user) : route('mes.system.users.store') }}">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">姓名 <span style="color:#d6336c">*</span></label>
                    <input name="name" class="form-control form-control-sm" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">用户名（登录用，可留空）</label>
                    <input name="username" class="form-control form-control-sm" value="{{ old('username', $user->username) }}" placeholder="admin 或 admin@mes.demo">
                    <div style="font-size:12px; color:#8a97a8; margin-top:4px">留空则只能用邮箱登录</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">邮箱（登录用） <span style="color:#d6336c">*</span></label>
                    <input name="email" type="email" class="form-control form-control-sm" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">工号</label>
                    <input name="employee_no" class="form-control form-control-sm" value="{{ old('employee_no', $user->employee_no) }}" placeholder="JG-0001">
                </div>
                <div class="col-md-6">
                    <label class="form-label">部门</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">未分配</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected((int) old('department_id', $user->department_id) === $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">岗位</label>
                    <select name="position_id" class="form-select form-select-sm">
                        <option value="">未分配</option>
                        @foreach ($positions as $p)
                            <option value="{{ $p->id }}" @selected((int) old('position_id', $user->position_id) === $p->id)>{{ $p->name }}{{ $p->department ? '（' . $p->department->name . '）' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">角色</label>
                    <select name="role" class="form-select form-select-sm">
                        @foreach ($roles as $key => $label)
                            <option value="{{ $key }}" @selected(old('role', $user->roles->first()?->name) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="active" @selected(old('status', $user->status ?: 'active') === 'active')>启用</option>
                        <option value="disabled" @selected(old('status', $user->status) === 'disabled')>停用</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">密码 {{ $isEdit ? '（留空不改）' : '' }} @if (! $isEdit)<span style="color:#d6336c">*</span>@endif</label>
                    <input name="password" type="password" class="form-control form-control-sm" value="" autocomplete="new-password"
                           placeholder="{{ $isEdit ? '不改就留空' : '至少 6 位' }}" @if (! $isEdit) required @endif>
                </div>
            </div>

            <div style="margin-top:18px; display:flex; gap:8px">
                <button class="btn btn-sm btn-primary">{{ $isEdit ? '保存' : '创建账号' }}</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.system.users.index') }}">取消</a>
            </div>
            <div style="margin-top:10px; font-size:12px; color:#8a97a8">
                提示：本页不预填任何口令，密码由你填写；演示环境对外公布的统一口令只出现在产品首页的演示账号卡上。
            </div>
        </form>
    </div>
@endsection
