@extends('shop::layouts.app')

@section('title', '商品类别')
@section('heading', '商品类别')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $categories->count() }} 个类别</p>
        <a href="{{ route('shop.categories.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新增类别
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @error('destroy')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="category-table-caption" class="visually-hidden">商品类别列表</caption>
                <thead>
                    <tr>
                        <th scope="col">编码</th>
                        <th scope="col">名称</th>
                        <th scope="col">上级类别</th>
                        <th scope="col">排序</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->code }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->parent?->name ?? '—' }}</td>
                            <td>{{ $category->sort }}</td>
                            <td>
                                @if ($category->is_active)
                                    <span class="badge text-bg-success">启用</span>
                                @else
                                    <span class="badge text-bg-secondary">停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $category->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.categories.destroy', $category) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除类别 {{ $category->name }} 吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $category->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">暂无类别，点击「新增类别」创建</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
