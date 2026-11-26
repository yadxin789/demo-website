@extends('shop::layouts.app')

@section('title', '价格表')
@section('heading', '价格表与阶梯价格')

@section('content')
    @php
        $typeLabels = ['sale' => '售价表', 'purchase' => '采购价表'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $lists->count() }} 张价格表</p>
        <a href="{{ route('shop.price-lists.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新增价格表
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="price-list-caption" class="visually-hidden">价格表列表</caption>
                <thead>
                    <tr>
                        <th scope="col">名称</th>
                        <th scope="col">类型</th>
                        <th scope="col">币种</th>
                        <th scope="col" class="text-end">价格行数</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lists as $list)
                        <tr>
                            <td>{{ $list->name }}</td>
                            <td>{{ $typeLabels[$list->type] ?? $list->type }}</td>
                            <td>{{ $list->currency }}</td>
                            <td class="text-end">{{ $list->items_count }}</td>
                            <td>
                                @if ($list->is_active)
                                    <span class="badge text-bg-success">启用</span>
                                @else
                                    <span class="badge text-bg-secondary">停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.price-lists.edit', $list) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $list->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.price-lists.destroy', $list) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除价格表 {{ $list->name }} 及其阶梯价吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $list->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">暂无价格表</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
