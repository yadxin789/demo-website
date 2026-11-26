@extends('shop::layouts.app')

@section('title', '收货入库单')
@section('heading', '收货入库单')

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'done' => '已入库', 'cancelled' => '已取消'];
        $badges = ['draft' => 'text-bg-secondary', 'done' => 'text-bg-success', 'cancelled' => 'text-bg-danger'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $receipts->total() }} 张收货单</p>
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
                <caption id="receipt-table-caption" class="visually-hidden">收货单列表</caption>
                <thead>
                    <tr>
                        <th scope="col">单号</th>
                        <th scope="col">采购单号</th>
                        <th scope="col">供应商</th>
                        <th scope="col">仓库</th>
                        <th scope="col">收货日期</th>
                        <th scope="col">状态</th>
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
                            <td>
                                <span class="badge {{ $badges[$receipt->status] ?? 'text-bg-secondary' }}">
                                    {{ $statusLabels[$receipt->status] ?? $receipt->status }}
                                </span>
                            </td>
                            <td class="text-end">{{ $receipt->lines_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('shop.receipts.show', $receipt) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-eye"></i><span class="visually-hidden">查看 {{ $receipt->code }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暂无收货单</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $receipts->links() }}
    </div>
@endsection
