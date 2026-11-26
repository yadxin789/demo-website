@extends('shop::layouts.app')

@section('title', "退货单 {$salesReturn->code}")
@section('heading', "退货单 {$salesReturn->code}")

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'done' => '已退货', 'cancelled' => '已取消'];
        $badges = ['draft' => 'text-bg-secondary', 'done' => 'text-bg-success', 'cancelled' => 'text-bg-danger'];
        $sourceLabels = ['sales_order' => '销售订单', 'pos_order' => 'POS 订单'];
        $refundLabels = ['credit' => '冲应收', 'cash' => '退现金', 'bank' => '退银行'];
        $sourceCode = $salesReturn->salesOrder?->code ?? $salesReturn->posOrder?->code ?? '—';
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="text-muted mb-1">
                来源：{{ $sourceLabels[$salesReturn->source_type] ?? $salesReturn->source_type }} · 原单：{{ $sourceCode }} ·
                客户：{{ $salesReturn->customer?->name ?? '散客' }} · 仓库：{{ $salesReturn->warehouse?->name ?? '—' }} ·
                退货日期：{{ optional($salesReturn->return_date)->format('Y-m-d') }}
            </p>
            <span class="badge {{ $badges[$salesReturn->status] ?? 'text-bg-secondary' }}">
                {{ $statusLabels[$salesReturn->status] ?? $salesReturn->status }}
            </span>
            <span class="text-muted small ms-2">操作人：{{ $salesReturn->operator?->name ?? '—' }}</span>
            <span class="text-muted small ms-2">退款方式：{{ $refundLabels[$salesReturn->refund_method] ?? '—' }}</span>
            @if ($salesReturn->reason)
                <span class="text-muted small ms-2">原因：{{ $salesReturn->reason }}</span>
            @endif
        </div>
        <a href="{{ route('shop.sales-returns.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>返回列表
        </a>
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
                <caption class="visually-hidden">退货明细行</caption>
                <thead>
                    <tr>
                        <th scope="col">商品</th>
                        <th scope="col">批次</th>
                        <th scope="col" class="text-end">退货数量</th>
                        <th scope="col" class="text-end">折后单价</th>
                        <th scope="col" class="text-end">税额</th>
                        <th scope="col" class="text-end">行合计</th>
                        <th scope="col">原因</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesReturn->lines as $line)
                        <tr>
                            <td>
                                {{ $line->product?->code ?? '—' }}
                                <span class="text-muted small d-block">{{ $line->product?->name ?? '商品已删除' }}</span>
                            </td>
                            <td>{{ $line->lot_no ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $line->qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $line->unit_price, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->tax_amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->line_total, 2) }}</td>
                            <td>{{ $line->reason ?? '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="table-light">
                        <th colspan="5" class="text-end">退款合计</th>
                        <td class="text-end fw-bold">{{ number_format((float) $salesReturn->refund_amount, 2) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($salesReturn->isEditable())
        <form method="post" action="{{ route('shop.sales-returns.post', ['salesReturn' => $salesReturn->id]) }}"
              onsubmit="return confirm('确认过账该退货单？过账后商品回库、收入与成本红字冲回，不可撤销。');">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check me-1"></i>过账退货
            </button>
        </form>
    @endif
@endsection
