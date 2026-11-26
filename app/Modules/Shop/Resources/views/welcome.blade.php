@extends('shop::layouts.app')

@section('title', '系统首页')
@section('heading', config('shop.name'))

@section('content')
    <div class="card">
        <h1 class="h4 mb-2">{{ config('shop.name') }}</h1>
        <p class="text-muted mb-3">Laravel 12 · PHP 8.3 · MySQL 8.4 · Bootstrap 5 · Font Awesome 6（全部静态资源本地托管，离线可用）</p>

        <div class="d-flex flex-wrap gap-2 align-center">
            <span class="badge text-bg-primary"><i class="fa-solid fa-box"></i> 商品库存</span>
            <span class="badge text-bg-success"><i class="fa-solid fa-cash-register"></i> POS 收银</span>
            <span class="badge text-bg-info"><i class="fa-solid fa-book-open"></i> 财务会计</span>
            <span class="badge text-bg-warning"><i class="fa-solid fa-shield-halved"></i> 操作审计</span>
        </div>

        <dl class="row mt-3 mb-0">
            <dt class="col-sm-3">Bootstrap</dt>
            <dd class="col-sm-9">{{ config('shop.assets.bootstrap.version') }}</dd>
            <dt class="col-sm-3">Font Awesome</dt>
            <dd class="col-sm-9">{{ config('shop.assets.fontawesome.version') }}</dd>
            <dt class="col-sm-3">Chart.js</dt>
            <dd class="col-sm-9">{{ config('shop.assets.chartjs.version') }}</dd>
            <dt class="col-sm-3">JsBarcode</dt>
            <dd class="col-sm-9">{{ config('shop.assets.jsbarcode.version') }}</dd>
        </dl>
    </div>
@endsection
