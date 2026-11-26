@extends('shop::layouts.app')

@section('title', '新增库位')
@section('heading', '新增库位')

@section('content')
    <div class="card" style="max-inline-size:36rem;">
        <form method="post" action="{{ route('shop.locations.store') }}" aria-label="新增库位表单">
            @include('shop::master.locations._form')
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存</button>
                <a href="{{ route('shop.locations.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
