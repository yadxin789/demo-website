@extends('shop::layouts.app')

@section('title', '编辑税率')
@section('heading', '编辑税率')

@section('content')
    <div class="card" style="max-inline-size:36rem;">
        <form method="post" action="{{ route('shop.taxes.update', $tax) }}" aria-label="编辑税率表单">
            @include('shop::master.taxes._form', ['method' => 'PATCH'])
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存修改</button>
                <a href="{{ route('shop.taxes.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
