@extends('shop::layouts.app')

@section('title', '选择收货单 · 采购退货')
@section('heading', '选择要退货的收货单')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">仅已入库的收货单可办理采购退货</p>
        <a href="{{ route('shop.purchase-returns.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>返回退货列表
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">可退货收货单列表</caption>
                <thead>
                    <tr>
                        <th scope="col">收货单号</th>
                        <th scope="col">采购单号</th>
                        <th scope="col">供应商</th>
                        <th scope="col">仓库</th>
                        <th scope="col">收货日期</th>
                        <th scope="col" class="text-end">行数</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                        <tr>
                            <td>{{ $receipt->code }}</td>
                            <td>{{ $receipt->purchaseOrder?->code ?? '—' }}</td>
                            <td>{{ $receipt->supplier?->name ?? '—' }}</td>
                            <td>{{ $receipt->warehouse?->name ?? '—' }}</td>
                            <td>{{ optional($receipt->receipt_date)->format('Y-m-d') }}</td>
                            <td class="text-end">{{ $receipt->lines->count() }}</td>
                            <td class="text-end">
                                <a href="{{ route('shop.purchase-returns.create', ['receipt' => $receipt->id]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-arrow-right me-1"></i>选择
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">暂无可退货的收货单</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
