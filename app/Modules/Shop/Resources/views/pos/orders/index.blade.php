@extends('shop::layouts.app')

@section('title', 'POS 订单')
@section('heading', 'POS 订单')

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'completed' => '已结账', 'refunded' => '已退款'];
        $badges = ['draft' => 'text-bg-secondary', 'completed' => 'text-bg-success', 'refunded' => 'text-bg-danger'];
    @endphp

    <p class="text-muted mb-3">共 {{ $orders->total() }} 张 POS 小票</p>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="pos-order-caption" class="visually-hidden">POS 订单列表</caption>
                <thead>
                    <tr>
                        <th scope="col">单号</th>
                        <th scope="col">终端</th>
                        <th scope="col">收银员</th>
                        <th scope="col">客户</th>
                        <th scope="col">下单时间</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">行数</th>
                        <th scope="col" class="text-end">应收</th>
                        <th scope="col" class="text-end">实收</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>{{ $order->code }}</td>
                            <td>{{ $order->station?->name ?? '—' }}</td>
                            <td>{{ $order->cashier?->name ?? '—' }}</td>
                            <td>{{ $order->customer?->name ?? '散客' }}</td>
                            <td>{{ optional($order->order_date)->format('Y-m-d H:i') }}</td>
                            <td>
                                <span class="badge {{ $badges[$order->status] ?? 'text-bg-secondary' }}">
                                    {{ $statusLabels[$order->status] ?? $order->status }}
                                </span>
                            </td>
                            <td class="text-end">{{ $order->lines_count }}</td>
                            <td class="text-end">{{ number_format((float) $order->grand_total, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $order->paid_amount, 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('shop.pos.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-eye"></i><span class="visually-hidden">查看 {{ $order->code }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">暂无 POS 订单</td>
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
