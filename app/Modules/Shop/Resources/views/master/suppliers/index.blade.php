@extends('shop::layouts.app')

@section('title', '供应商')
@section('heading', '供应商')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $suppliers->count() }} 家供应商</p>
        <div class="d-flex gap-2">
            <a href="{{ route('shop.exchange.import', 'suppliers') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-upload me-1"></i>导入
            </a>
            <a href="{{ route('shop.exchange.export', 'suppliers') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-file-export me-1"></i>导出
            </a>
            <a href="{{ route('shop.suppliers.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i>新增供应商
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="supplier-table-caption" class="visually-hidden">供应商列表</caption>
                <thead>
                    <tr>
                        <th scope="col">编码</th>
                        <th scope="col">名称</th>
                        <th scope="col">联系人</th>
                        <th scope="col">电话</th>
                        <th scope="col">邮箱</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td>{{ $supplier->code }}</td>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->contact_name ?: '—' }}</td>
                            <td>{{ $supplier->phone ?: '—' }}</td>
                            <td>{{ $supplier->email ?: '—' }}</td>
                            <td>
                                @if ($supplier->is_active)
                                    <span class="badge text-bg-success">启用</span>
                                @else
                                    <span class="badge text-bg-secondary">停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $supplier->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.suppliers.destroy', $supplier) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除供应商 {{ $supplier->name }} 吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $supplier->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">暂无供应商</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
