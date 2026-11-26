@extends('shop::layouts.app')

@section('title', '采购订单')
@section('heading', '采购订单')

@section('content')
    @php
        $statusLabels = [
            'draft' => '草稿', 'confirmed' => '已确认', 'received' => '已收货',
            'partial' => '部分收货', 'closed' => '已关闭', 'cancelled' => '已取消',
        ];
        $badges = [
            'draft' => 'text-bg-secondary', 'confirmed' => 'text-bg-primary',
            'received' => 'text-bg-success', 'partial' => 'text-bg-info',
            'closed' => 'text-bg-dark', 'cancelled' => 'text-bg-danger',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $orders->total() }} 张采购订单</p>
        <a href="{{ route('shop.purchase-orders.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新建采购单
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
                <caption id="po-table-caption" class="visually-hidden">采购订单列表</caption>
                <thead>
                    <tr>
                        <th scope="col">单号</th>
                        <th scope="col">供应商</th>
                        <th scope="col">仓库</th>
                        <th scope="col">订单日期</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">行数</th>
                        <th scope="col" class="text-end">价税合计</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>{{ $order->code }}</td>
                            <td>{{ $order->supplier?->name ?? '—' }}</td>
                            <td>{{ $order->warehouse?->name ?? '—' }}</td>
                            <td>{{ optional($order->order_date)->format('Y-m-d') }}</td>
                            <td>
                                <span class="badge {{ $badges[$order->status] ?? 'text-bg-secondary' }}">
                                    {{ $statusLabels[$order->status] ?? $order->status }}
                                </span>
                            </td>
                            <td class="text-end">{{ $order->lines_count }}</td>
                            <td class="text-end">{{ number_format((float) $order->grand_total, 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('shop.purchase-orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-eye"></i><span class="visually-hidden">查看 {{ $order->code }}</span>
                                </a>
                                @if ($order->isEditable())
                                    <a href="{{ route('shop.purchase-orders.edit', $order) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $order->code }}</span>
                                    </a>
                                    <form method="post" action="{{ route('shop.purchase-orders.destroy', $order) }}" class="d-inline"
                                          onsubmit="return confirm('确定删除采购单 {{ $order->code }} 吗？')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $order->code }}</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暂无采购订单</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $orders->links() }}
    </div>
@endsection
