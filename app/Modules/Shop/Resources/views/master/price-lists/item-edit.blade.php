@extends('shop::layouts.app')

@section('title', '编辑阶梯价格')
@section('heading', '编辑阶梯价格')

@section('content')
    <div class="card" style="max-inline-size:52rem;">
        <form method="post" action="{{ route('shop.price-lists.items.update', [$price_list, $item]) }}" aria-label="编辑阶梯价格表单">
            @include('shop::master.price-lists._item_form')
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存修改</button>
                <a href="{{ route('shop.price-lists.edit', $price_list) }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
@endsection
