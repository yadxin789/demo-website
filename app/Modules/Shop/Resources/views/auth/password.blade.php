@extends('shop::layouts.app')

@section('title', '修改密码')
@section('heading', '修改密码')

@section('content')
    <div class="card" style="max-inline-size:30rem;">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <form method="post" action="{{ route('shop.password.update') }}" aria-label="修改密码表单">
            @csrf

            <div class="mb-3">
                <label for="current_password" class="form-label">当前密码</label>
                <input type="password"
                       id="current_password"
                       name="current_password"
                       class="form-control @error('current_password') is-invalid @enderror"
                       autocomplete="current-password"
                       required>
                @error('current_password')
                    <div class="invalid-feedback" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">新密码</label>
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="new-password"
                       minlength="8"
                       required>
                <div class="form-text">至少 8 位字符。</div>
                @error('password')
                    <div class="invalid-feedback" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">确认新密码</label>
                <input type="password"
                       id="password_confirmation"
                       name="password_confirmation"
                       class="form-control"
                       autocomplete="new-password"
                       required>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-key me-1"></i>更新密码
            </button>
        </form>
    </div>
@endsection
