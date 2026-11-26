@extends('shop::layouts.app')

@section('title', "收货单 {$receipt->code}")
@section('heading', "收货单 {$receipt->code}")

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'done' => '已入库', 'cancelled' => '已取消'];
        $badges = ['draft' => 'text-bg-secondary', 'done' => 'text-bg-success', 'cancelled' => 'text-bg-danger'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="text-muted mb-1">
                采购单：{{ $receipt->purchaseOrder?->code ?? '—' }} · 供应商：{{ $receipt->supplier?->name ?? '—' }} ·
                仓库：{{ $receipt->warehouse?->name ?? '—' }} · 收货日期：{{ optional($receipt->receipt_date)->format('Y-m-d') }}
            </p>
            <span class="badge {{ $badges[$receipt->status] ?? 'text-bg-secondary' }}">{{ $statusLabels[$receipt->status] ?? $receipt->status }}</span>
            <span class="text-muted small ms-2">操作人：{{ $receipt->operator?->name ?? '—' }}</span>
        </div>
        <a href="{{ route('shop.receipts.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>返回列表</a>
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
                <caption class="visually-hidden">收货明细行</caption>
                <thead>
                    <tr>
                        <th scope="col">商品</th>
                        <th scope="col">库位</th>
                        <th scope="col">批次</th>
                        <th scope="col">序列号</th>
                        <th scope="col" class="text-end">数量</th>
                        <th scope="col" class="text-end">单位成本</th>
                        <th scope="col">备注</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($receipt->lines as $line)
                        <tr>
                            <td>
                                {{ $line->product?->code ?? '—' }}
                                <span class="text-muted small d-block">{{ $line->product?->name ?? '商品已删除' }}</span>
                            </td>
                            <td>{{ $line->location?->code ?? '—' }}</td>
                            <td>{{ $line->lot_no ?? '—' }}</td>
                            <td>{{ $line->serial_no ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $line->qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $line->unit_cost, 2) }}</td>
                            <td>{{ $line->line_note ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
