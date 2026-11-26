@extends('shop::layouts.app')

@section('title', '客户')
@section('heading', '客户')

@section('content')
    @php
        $levelLabels = ['normal' => '普通', 'silver' => '银卡', 'gold' => '金卡'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $customers->count() }} 位客户</p>
        <div class="d-flex gap-2">
            <a href="{{ route('shop.exchange.import', 'customers') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-upload me-1"></i>导入
            </a>
            <a href="{{ route('shop.exchange.export', 'customers') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-file-export me-1"></i>导出
            </a>
            <a href="{{ route('shop.customers.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i>新增客户
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="customer-table-caption" class="visually-hidden">客户列表</caption>
                <thead>
                    <tr>
                        <th scope="col">编码</th>
                        <th scope="col">名称</th>
                        <th scope="col">联系人</th>
                        <th scope="col">电话</th>
                        <th scope="col">等级</th>
                        <th scope="col" class="text-end">信用额度</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->code }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->contact_name ?: '—' }}</td>
                            <td>{{ $customer->phone ?: '—' }}</td>
                            <td>{{ $levelLabels[$customer->membership_level] ?? $customer->membership_level }}</td>
                            <td class="text-end">{{ number_format((float) $customer->credit_limit, 2) }}</td>
                            <td>
                                @if ($customer->is_active)
                                    <span class="badge text-bg-success">启用</span>
                                @else
                                    <span class="badge text-bg-secondary">停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.customers.edit', $customer) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $customer->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.customers.destroy', $customer) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除客户 {{ $customer->name }} 吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $customer->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暂无客户</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
