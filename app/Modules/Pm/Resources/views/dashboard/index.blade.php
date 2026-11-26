@extends('pm::layouts.app')

@section('title', '工作台 · 研发项目管理')

@section('content')
    <div class="page-head">
        <h1>工作台</h1>
        <p>研发项目管理 · {{ config('products.products.pm.company') }}</p>
    </div>

    <div class="empty-note">
        本产品业务页面将在后续阶段接入（当前为骨架验收版本）。
    </div>
@endsection