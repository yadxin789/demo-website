@extends('shop::layouts.app')

@section('title', '编辑商品')
@section('heading', '编辑商品')

@section('content')
    <div class="card" style="max-inline-size:52rem;">
        <form method="post" action="{{ route('shop.products.update', $product) }}" enctype="multipart/form-data" aria-label="编辑商品表单">
            @include('shop::master.products._form', ['method' => 'PATCH'])
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存修改</button>
                <a href="{{ route('shop.products.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
