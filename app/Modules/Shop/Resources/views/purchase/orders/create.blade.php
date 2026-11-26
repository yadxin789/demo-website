@extends('shop::layouts.app')

@section('title', '新建采购单')
@section('heading', '新建采购单')

@section('content')
    <div class="card">
        <form method="post" action="{{ route('shop.purchase-orders.store') }}" aria-label="新建采购单表单">
            @include('shop::purchase.orders._form')
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存草稿</button>
                <a href="{{ route('shop.purchase-orders.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
