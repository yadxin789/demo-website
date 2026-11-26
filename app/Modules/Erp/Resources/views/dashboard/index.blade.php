@extends('erp::layouts.app')

@section('title', '工作台 · 电子制造 ERP')

@section('content')
    <div class="page-head">
        <h1>工作台</h1>
        <p>电子制造 ERP · {{ config('products.products.erp.company') }}</p>
    </div>

    <div class="empty-note">
        本产品业务页面将在后续阶段接入（当前为骨架验收版本）。
    </div>
@endsection