@extends('shop::layouts.app')

@section('title', '工作台 · 百货零售 ERP')

@section('content')
    <div class="page-head">
        <h1>工作台</h1>
        <p>百货零售 ERP · {{ config('products.products.shop.company') }}</p>
    </div>

    <div class="empty-note">
        本产品业务页面将在后续阶段接入（当前为骨架验收版本）。
    </div>
@endsection