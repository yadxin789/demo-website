@extends('shop::layouts.app')

@section('title', '新增价格表')
@section('heading', '新增价格表')

@section('content')
    <div class="card" style="max-inline-size:36rem;">
        <form method="post" action="{{ route('shop.price-lists.store') }}" aria-label="新增价格表表单">
            @include('shop::master.price-lists._form')
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存</button>
                <a href="{{ route('shop.price-lists.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
