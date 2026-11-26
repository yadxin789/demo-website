@extends('shop::layouts.app')

@section('title', '仓库')
@section('heading', '仓库')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $warehouses->count() }} 个仓库（单仓库多库位）</p>
        <a href="{{ route('shop.warehouses.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新增仓库
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="warehouse-table-caption" class="visually-hidden">仓库列表</caption>
                <thead>
                    <tr>
                        <th scope="col">名称</th>
                        <th scope="col">编码</th>
                        <th scope="col">地址</th>
                        <th scope="col">库位数</th>
                        <th scope="col">默认</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($warehouses as $warehouse)
                        <tr>
                            <td>{{ $warehouse->name }}</td>
                            <td>{{ $warehouse->code }}</td>
                            <td>{{ $warehouse->address ?: '—' }}</td>
                            <td>{{ $warehouse->locations_count }}</td>
                            <td>
                                @if ($warehouse->is_default)
                                    <span class="badge text-bg-primary">默认</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($warehouse->is_active)
                                    <span class="badge text-bg-success">启用</span>
                                @else
                                    <span class="badge text-bg-secondary">停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.warehouses.edit', $warehouse) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $warehouse->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.warehouses.destroy', $warehouse) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除仓库 {{ $warehouse->name }} 吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $warehouse->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">暂无仓库</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
