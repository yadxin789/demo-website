@extends('shop::layouts.app')

@section('title', "编辑采购单 {$purchaseOrder->code}")
@section('heading', "编辑采购单 {$purchaseOrder->code}")

@section('content')
    <div class="card">
        <form method="post" action="{{ route('shop.purchase-orders.update', $purchaseOrder) }}" aria-label="编辑采购单表单">
            @include('shop::purchase.orders._form', ['method' => 'PATCH', 'order' => $purchaseOrder])
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存</button>
                <a href="{{ route('shop.purchase-orders.show', $purchaseOrder) }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
