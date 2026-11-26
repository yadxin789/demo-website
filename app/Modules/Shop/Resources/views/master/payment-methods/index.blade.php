@extends('shop::layouts.app')

@section('title', '支付方式')
@section('heading', '支付方式')

@section('content')
    @php
        $typeLabels = [
            'cash' => '现金',
            'bank' => '银行存款',
            'electronic' => '电子支付',
            'card' => '银行卡',
            'coupon' => '储值券',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $methods->count() }} 种支付方式</p>
        <a href="{{ route('shop.payment-methods.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新增支付方式
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="payment-method-table-caption" class="visually-hidden">支付方式列表</caption>
                <thead>
                    <tr>
                        <th scope="col">名称</th>
                        <th scope="col">编码</th>
                        <th scope="col">类型</th>
                        <th scope="col">默认</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($methods as $method)
                        <tr>
                            <td>{{ $method->name }}</td>
                            <td>{{ $method->code }}</td>
                            <td>{{ $typeLabels[$method->type] ?? $method->type }}</td>
                            <td>
                                @if ($method->is_default)
                                    <span class="badge text-bg-primary">默认</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($method->is_active)
                                    <span class="badge text-bg-success">启用</span>
                                @else
                                    <span class="badge text-bg-secondary">停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.payment-methods.edit', $method) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $method->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.payment-methods.destroy', $method) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除支付方式 {{ $method->name }} 吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $method->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">暂无支付方式</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
