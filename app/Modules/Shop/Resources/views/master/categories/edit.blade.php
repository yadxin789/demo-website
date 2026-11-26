@extends('shop::layouts.app')

@section('title', '编辑类别')
@section('heading', '编辑类别')

@section('content')
    <div class="card" style="max-inline-size:40rem;">
        <form method="post" action="{{ route('shop.categories.update', $category) }}" aria-label="编辑类别表单">
            @include('shop::master.categories._form', ['method' => 'PATCH'])
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存修改</button>
                <a href="{{ route('shop.categories.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
