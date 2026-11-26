@extends('shop::layouts.app')

@section('title', '新增支付方式')
@section('heading', '新增支付方式')

@section('content')
    <div class="card" style="max-inline-size:36rem;">
        <form method="post" action="{{ route('shop.payment-methods.store') }}" aria-label="新增支付方式表单">
            @include('shop::master.payment-methods._form')
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存</button>
                <a href="{{ route('shop.payment-methods.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
