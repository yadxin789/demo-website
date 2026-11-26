@extends('shop::layouts.app')

@section('title', '登录 · 百货零售 ERP')

@section('content')
    <div class="login-card">
        <div class="login-head">
            <div class="login-icon"> ERP</div>
            <h1>百货零售 ERP</h1>
            <p>请登录后进入系统</p>
        </div>

        <form method="POST" action="{{ route('shop.root') }}">
            @csrf
            <label class="field">
                <span>用户名 / 邮箱</span>
                <input type="text" name="email" value="{{ config('products.products.shop.demo_account.email') }}" autocomplete="username">
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