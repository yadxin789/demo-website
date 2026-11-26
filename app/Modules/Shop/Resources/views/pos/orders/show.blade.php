@extends('shop::layouts.app')

@section('title', "小票 {$order->code}")
@section('heading', "小票 {$order->code}")

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'completed' => '已结账', 'refunded' => '已退款'];
    @endphp

    <div class="mb-3 d-flex gap-2">
        <a href="{{ route('shop.pos.orders.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> 返回列表
        </a>
        <a href="{{ route('shop.pos.orders.receipt', $order) }}" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-print"></i> 浏览器打印小票
        </a>
    </div>

    <div class="card mb-3">
        <dl class="row mb-0">
            <dt class="col-sm-2">单号</dt>
            <dd class="col-sm-4">{{ $order->code }}</dd>
            <dt class="col-sm-2">状态</dt>
            <dd class="col-sm-4">{{ $statusLabels[$order->status] ?? $order->status }}</dd>

            <dt class="col-sm-2">终端</dt>
            <dd class="col-sm-4">{{ $order->station?->name ?? '—' }}</dd>
            <dt class="col-sm-2">收银员</dt>
            <dd class="col-sm-4">{{ $order->cashier?->name ?? '—' }}</dd>

            <dt class="col-sm-2">客户</dt>
            <dd class="col-sm-4">{{ $order->customer?->name ?? '散客' }}</dd>
            <dt class="col-sm-2">下单时间</dt>
            <dd class="col-sm-4">{{ optional($order->order_date)->format('Y-m-d H:i:s') }}</dd>
        </dl>
    </div>

    <div class="card p-0 mb-3">
        <div class="table-wrapper">
            <table class="table table-sm mb-0">
                <caption class="visually-hidden">小票商品明细</caption>
                <thead>
                    <tr>
                        <th scope="col">商品</th>
                        <th scope="col">编码</th>
                        <th scope="col" class="text-end">数量</th>
                        <th scope="col" class="text-end">原始价</th>
                        <th scope="col" class="text-end">成交价</th>
                        <th scope="col" class="text-end">折扣率</th>
                        <th scope="col" class="text-end">税额</th>
                        <th scope="col" class="text-end">小计</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td>{{ $line->product?->name ?? '商品已删除' }}</td>
                            <td>{{ $line->product?->code ?? '—' }}</td>
                            <td class="text-end">{{ $line->qty }}</td>
                            <td class="text-end">{{ number_format((float) $line->original_price, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->unit_price, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->discount_rate, 2) }}%</td>
                            <td class="text-end">{{ number_format((float) $line->tax_amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->subtotal ?? ((float) $line->qty * (float) $line->unit_price), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-0 mb-3">
        <div class="table-wrapper">
            <table class="table table-sm mb-0">
                <caption class="visually-hidden">支付记录</caption>
                <thead>
                    <tr>
                        <th scope="col">支付方式</th>
                        <th scope="col" class="text-end">金额</th>
                        <th scope="col">参考号</th>
                        <th scope="col">操作员</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->payments as $payment)
                        <tr>
                            <td>{{ $payment->method?->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ $payment->reference ?? '—' }}</td>
                            <td>{{ $payment->operator?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <dl class="row mb-0">
            <dt class="col-sm-3">商品合计（折后）</dt>
            <dd class="col-sm-3 text-end">{{ number_format((float) $order->subtotal, 2) }}</dd>
            <dt class="col-sm-3">折扣</dt>
            <dd class="col-sm-3 text-end">{{ number_format((float) $order->discount_amount, 2) }}</dd>

            <dt class="col-sm-3">税额</dt>
            <dd class="col-sm-3 text-end">{{ number_format((float) $order->tax_amount, 2) }}</dd>
            <dt class="col-sm-3">应收合计</dt>
            <dd class="col-sm-3 text-end">{{ number_format((float) $order->grand_total, 2) }}</dd>

            <dt class="col-sm-3">实收</dt>
            <dd class="col-sm-3 text-end">{{ number_format((float) $order->paid_amount, 2) }}</dd>
            <dt class="col-sm-3">找零</dt>
            <dd class="col-sm-3 text-end">{{ number_format((float) $order->change_amount, 2) }}</dd>
        </dl>
    </div>
@endsection
