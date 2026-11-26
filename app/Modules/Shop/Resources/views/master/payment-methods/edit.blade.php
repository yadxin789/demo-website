@extends('shop::layouts.app')

@section('title', '编辑支付方式')
@section('heading', '编辑支付方式')

@section('content')
    <div class="card" style="max-inline-size:36rem;">
        <form method="post" action="{{ route('shop.payment-methods.update', $payment_method) }}" aria-label="编辑支付方式表单">
            @include('shop::master.payment-methods._form', ['method' => 'PATCH'])
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存修改</button>
                <a href="{{ route('shop.payment-methods.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
