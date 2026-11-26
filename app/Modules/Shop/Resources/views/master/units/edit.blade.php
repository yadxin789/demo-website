@extends('shop::layouts.app')

@section('title', '编辑单位')
@section('heading', '编辑计量单位')

@section('content')
    <div class="card" style="max-inline-size:32rem;">
        <form method="post" action="{{ route('shop.units.update', $unit) }}" aria-label="编辑单位表单">
            @include('shop::master.units._form', ['method' => 'PATCH'])
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存修改</button>
                <a href="{{ route('shop.units.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
