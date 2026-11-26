@extends('shop::layouts.app')

@section('title', '个人资料')
@section('heading', '个人资料')

@section('content')
    <div class="d-flex flex-wrap gap-3 align-items-start">
        <section class="card flex-grow-1" style="max-inline-size:34rem;" aria-label="基本资料">
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            <h2 class="h6 mb-3">基本资料</h2>

            <form method="post" action="{{ route('shop.profile.update') }}" aria-label="个人资料表单">
                @csrf
                @method('PATCH')

                <div class="mb-3">
                    <label for="name" class="form-label">姓名 <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $user->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">用户名</label>
                    <input type="text" class="form-control" value="{{ $user->username }}" readonly disabled>
                    <div class="form-text">用户名由管理员维护，不能在此修改。</div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">邮箱 <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $user->email) }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">手机号</label>
                    <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $user->phone) }}">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存资料</button>
                    <a href="{{ route('shop.profile.avatar') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-image me-1"></i>更新头像</a>
                    <a href="{{ route('shop.password.show') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-key me-1"></i>重置密码</a>
                </div>
            </form>
        </section>

        <section class="card text-center" style="inline-size:18rem;" aria-label="当前头像">
            <h2 class="h6 mb-3">当前头像</h2>
            @include('shop::profile.partials.avatar-preview', ['user' => $user, 'size' => '7rem'])
            <a href="{{ route('shop.profile.avatar') }}" class="btn btn-outline-secondary btn-sm mt-3">
                <i class="fa-solid fa-image me-1"></i>更新头像
            </a>
        </section>
    </div>
@endsection
