@extends('hr::layouts.app')

@php
    $isEdit = $mode === 'edit';
@endphp

@section('title', $isEdit ? '编辑账号' : '新增账号')
@section('sub', $isEdit ? '修改账号资料与角色；密码留空则保持不变' : '新建 HR 登录账号，密码由你现场填写')

@section('content')
    <div class="hr-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 style="margin:0">{{ $isEdit ? '编辑账号：' . $user->name : '新增账号' }}</h3>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.system.users.index') }}">返回列表</a>
        </div>

        @if (session('error'))
            <div class="alert alert-warning py-2" style="font-size:13px">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger py-2" style="font-size:13px">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ $isEdit ? route('hr.system.users.profile', $user->id) : route('hr.system.users.store') }}">
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
                    <input name="username" class="form-control form-control-sm" value="{{ old('username', $user->username) }}" placeholder="admin 或 admin@hr.demo">
                    <div style="font-size:12px; color:#9a9aad; margin-top:4px">留空则只能用邮箱登录</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">邮箱（登录用） <span style="color:#d6336c">*</span></label>
                    <input name="email" type="email" class="form-control form-control-sm" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">工号</label>
                    <input name="employee_no" class="form-control form-control-sm" value="{{ old('employee_no', $user->employee_no) }}" placeholder="HR-0001">
                </div>
                <div class="col-md-4">
                    <label class="form-label">角色</label>
                    <select name="role" class="form-select form-select-sm">
                        @foreach ($roles as $r)
                            <option value="{{ $r }}" @selected(old('role', $user->role_code ?: 'employee') === $r)>{{ $roleText[$r] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="active" @selected(old('status', $user->status ?: 'active') === 'active')>正常</option>
                        <option value="disabled" @selected(old('status', $user->status) === 'disabled')>停用</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">密码 {{ $isEdit ? '（留空不改）' : '' }} @if (! $isEdit)<span style="color:#d6336c">*</span>@endif</label>
                    <input name="password" type="password" class="form-control form-control-sm" value="" autocomplete="new-password"
                           placeholder="{{ $isEdit ? '不改就留空' : '至少 6 位' }}" @if (! $isEdit) required @endif>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary">{{ $isEdit ? '保存' : '创建账号' }}</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.system.users.index') }}">取消</a>
            </div>
            <div style="font-size:12px; color:#9a9aad; margin-top:10px">
                本页不预填任何口令；演示账号的统一口令只出现在产品首页的演示账号卡上。
            </div>
        </form>
    </div>
@endsection
