@extends('shop::layouts.app')

@section('title', '编辑供应商')
@section('heading', '编辑供应商')

@section('content')
    <div class="card" style="max-inline-size:36rem;">
        <form method="post" action="{{ route('shop.suppliers.update', $supplier) }}" aria-label="编辑供应商表单">
            @include('shop::master.suppliers._form', ['method' => 'PATCH'])
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存修改</button>
                <a href="{{ route('shop.suppliers.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
