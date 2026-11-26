@extends('shop::layouts.app')

@section('title', '选择原单 · 销售退货')
@section('heading', '选择要退货的原单')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">仅已发货的销售订单与已完成的 POS 订单可办理退货</p>
        <a href="{{ route('shop.sales-returns.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>返回退货列表
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <h2 class="h5 mb-2">销售订单</h2>
    <div class="card p-0 mb-4">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">可退货销售订单</caption>
                <thead>
                    <tr>
                        <th scope="col">单号</th>
                        <th scope="col">客户</th>
                        <th scope="col" class="text-end">行数</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesOrders as $order)
                        <tr>
                            <td>{{ $order->code }}</td>
                            <td>{{ $order->customer?->name ?? '—' }}</td>
                            <td class="text-end">{{ $order->lines->count() }}</td>
                            <td class="text-end">
                                <a href="{{ route('shop.sales-returns.create', ['source' => 'sales_order', 'id' => $order->id]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-arrow-right me-1"></i>选择
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">暂无可退货销售订单</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="h5 mb-2">POS 订单</h2>
    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">可退货 POS 订单</caption>
                <thead>
                    <tr>
                        <th scope="col">单号</th>
                        <th scope="col">客户</th>
                        <th scope="col" class="text-end">行数</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($posOrders as $order)
                        <tr>
                            <td>{{ $order->code }}</td>
                            <td>{{ $order->customer?->name ?? '散客' }}</td>
                            <td class="text-end">{{ $order->lines->count() }}</td>
                            <td class="text-end">
                                <a href="{{ route('shop.sales-returns.create', ['source' => 'pos_order', 'id' => $order->id]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-arrow-right me-1"></i>选择
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">暂无可退货 POS 订单</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
