@extends('pm::layouts.app')

@section('title', '登录 · 研发项目管理')

@section('content')
    <div class="login-card">
        <div class="login-head">
            <div class="login-icon"></div>
            <h1>研发项目管理</h1>
            <p>请登录后进入系统</p>
        </div>

        <form method="POST" action="{{ route('pm.root') }}">
            @csrf
            <label class="field">
                <span>用户名 / 邮箱</span>
                <input type="text" name="email" value="{{ config('products.products.pm.demo_account.email') }}" autocomplete="username">
            </label>

            <label class="field">
                <span>密码</span>
                <input type="password" name="password" value="123456" autocomplete="current-password">
            </label>

            <button type="submit" class="btn-primary">登录</button>
        </form>

        <p class="login-note">演示账号已预填，直接点登录即可。</p>
    </div>
@endsection