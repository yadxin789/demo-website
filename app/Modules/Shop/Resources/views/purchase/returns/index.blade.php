@extends('shop::layouts.app')

@section('title', '采购退货单')
@section('heading', '采购退货单')

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'done' => '已退货', 'cancelled' => '已取消'];
        $badges = ['draft' => 'text-bg-secondary', 'done' => 'text-bg-success', 'cancelled' => 'text-bg-danger'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $returns->total() }} 张采购退货单</p>
        <a href="{{ route('shop.purchase-returns.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新建退货
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
                <caption id="return-table-caption" class="visually-hidden">采购退货单列表</caption>
                <thead>
                    <tr>
                        <th scope="col">单号</th>
                        <th scope="col">收货单号</th>
                        <th scope="col">供应商</th>
                        <th scope="col">仓库</th>
                        <th scope="col">退货日期</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">行数</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($returns as $return)
                        <tr>
                            <td>{{ $return->code }}</td>
                            <td>{{ $return->receipt?->code ?? '—' }}</td>
                            <td>{{ $return->supplier?->name ?? '—' }}</td>
                            <td>{{ $return->warehouse?->name ?? '—' }}</td>
                            <td>{{ optional($return->return_date)->format('Y-m-d') }}</td>
                            <td>
                                <span class="badge {{ $badges[$return->status] ?? 'text-bg-secondary' }}">
                                    {{ $statusLabels[$return->status] ?? $return->status }}
                                </span>
                            </td>
                            <td class="text-end">{{ $return->lines_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('shop.purchase-returns.show', ['purchaseReturn' => $return->id]) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-eye"></i><span class="visually-hidden">查看 {{ $return->code }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暂无采购退货单</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $returns->links() }}
    </div>
@endsection
