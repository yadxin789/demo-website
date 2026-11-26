@extends('shop::layouts.app')

@section('title', '新增单位')
@section('heading', '新增计量单位')

@section('content')
    <div class="card" style="max-inline-size:32rem;">
        <form method="post" action="{{ route('shop.units.store') }}" aria-label="新增单位表单">
            @include('shop::master.units._form')
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存</button>
                <a href="{{ route('shop.units.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
