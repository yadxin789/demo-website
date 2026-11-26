@extends('shop::layouts.app')

@section('title', '新增类别')
@section('heading', '新增类别')

@section('content')
    <div class="card" style="max-inline-size:40rem;">
        <form method="post" action="{{ route('shop.categories.store') }}" aria-label="新增类别表单">
            @include('shop::master.categories._form')
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存</button>
                <a href="{{ route('shop.categories.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
