@extends('shop::layouts.app')

@section('title', '新增供应商')
@section('heading', '新增供应商')

@section('content')
    <div class="card" style="max-inline-size:36rem;">
        <form method="post" action="{{ route('shop.suppliers.store') }}" aria-label="新增供应商表单">
            @include('shop::master.suppliers._form')
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存</button>
                <a href="{{ route('shop.suppliers.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
