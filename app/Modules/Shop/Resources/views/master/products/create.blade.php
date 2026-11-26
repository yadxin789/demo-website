@extends('shop::layouts.app')

@section('title', '新增商品')
@section('heading', '新增商品')

@section('content')
    <div class="card" style="max-inline-size:52rem;">
        <form method="post" action="{{ route('shop.products.store') }}" enctype="multipart/form-data" aria-label="新增商品表单">
            @include('shop::master.products._form')
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存</button>
                <a href="{{ route('shop.products.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
