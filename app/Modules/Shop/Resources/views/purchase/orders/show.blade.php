@extends('shop::layouts.app')

@section('title', "采购单 {$order->code}")
@section('heading', "采购单 {$order->code}")

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
        $actions = [
            'confirmed' => ['label' => '确认订单', 'class' => 'btn-primary'],
            'partial' => ['label' => '部分收货', 'class' => 'btn-info'],
            'received' => ['label' => '全部收货', 'class' => 'btn-success'],
            'closed' => ['label' => '关闭订单', 'class' => 'btn-dark'],
            'cancelled' => ['label' => '取消订单', 'class' => 'btn-outline-danger'],
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="text-muted mb-1">
                供应商：{{ $order->supplier?->name ?? '—' }} · 仓库：{{ $order->warehouse?->name ?? '—' }} ·
                订单日期：{{ optional($order->order_date)->format('Y-m-d') }} ·
                预计到货：{{ optional($order->expected_date)->format('Y-m-d') ?? '—' }}
            </p>
            <span class="badge {{ $badges[$order->status] ?? 'text-bg-secondary' }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
            @if ($order->note)
                <span class="text-muted small ms-2">{{ $order->note }}</span>
            @endif
        </div>
        <a href="{{ route('shop.purchase-orders.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>返回列表</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="card p-0 mb-3">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">采购单行明细</caption>
                <thead>
                    <tr>
                        <th scope="col">商品</th>
                        <th scope="col" class="text-end">数量</th>
                        <th scope="col" class="text-end">单价</th>
                        <th scope="col" class="text-end">折扣率%</th>
                        <th scope="col" class="text-end">净额</th>
                        <th scope="col" class="text-end">税额</th>
                        <th scope="col">备注</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td>
                                {{ $line->product?->code ?? '—' }}
                                <span class="text-muted small d-block">{{ $line->product?->name ?? '商品已删除' }}</span>
                            </td>
                            <td class="text-end">{{ number_format((float) $line->qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $line->unit_price, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->discount_rate, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->subtotal, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->tax_amount, 2) }}</td>
                            <td>{{ $line->line_note ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="4" class="text-end">合计</th>
                        <th scope="col" class="text-end">{{ number_format((float) $order->total_amount, 2) }}</th>
                        <th scope="col" class="text-end">{{ number_format((float) $order->tax_amount, 2) }}</th>
                        <td></td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="6" class="text-end">价税合计</th>
                        <th scope="col" class="text-end">{{ number_format((float) $order->grand_total, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        @if (in_array($order->status, ['confirmed', 'partial'], true))
            <a href="{{ route('shop.receipts.create', ['purchase_order' => $order->id]) }}" class="btn btn-info">
                <i class="fa-solid fa-truck-ramp-box me-1"></i>收货入库
            </a>
        @endif
        @if ($order->isEditable())
            <a href="{{ route('shop.purchase-orders.edit', $order) }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-pen me-1"></i>编辑
            </a>
        @endif
        @foreach ($actions as $status => $action)
            @if ($order->canTransitionTo($status))
                <form method="post" action="{{ route('shop.purchase-orders.transition', $order) }}" class="d-inline"
                      onsubmit="return confirm('确定{{ $action['label'] }}吗？')">
                    @csrf
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" class="btn {{ $action['class'] }}">{{ $action['label'] }}</button>
                </form>
            @endif
        @endforeach
    </div>
@endsection
