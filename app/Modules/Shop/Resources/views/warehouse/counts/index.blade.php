@extends('shop::layouts.app')

@section('title', '盘点单')
@section('heading', '盘点单')

@section('content')
    @php
        $statusLabels = [
            'draft' => '草稿',
            'counting' => '盘点中',
            'adjusted' => '已过账',
            'cancelled' => '已取消',
        ];
        $badges = [
            'draft' => 'text-bg-secondary',
            'counting' => 'text-bg-primary',
            'adjusted' => 'text-bg-success',
            'cancelled' => 'text-bg-danger',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $counts->total() }} 张盘点单</p>
        <a href="{{ route('shop.counts.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新建盘点单
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
                <caption id="count-table-caption" class="visually-hidden">盘点单列表</caption>
                <thead>
                    <tr>
                        <th scope="col">单号</th>
                        <th scope="col">仓库</th>
                        <th scope="col">库位</th>
                        <th scope="col">盘点日期</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">行数</th>
                        <th scope="col" class="text-end">盈亏金额</th>
                        <th scope="col">操作人</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($counts as $count)
                        @php $variance = (float) $count->lines->sum('variance_value'); @endphp
                        <tr>
                            <td>{{ $count->code }}</td>
                            <td>{{ $count->warehouse?->name ?? '—' }}</td>
                            <td>{{ $count->location?->code ?? '全部库位' }}</td>
                            <td>{{ optional($count->count_date)->format('Y-m-d') }}</td>
                            <td>
                                <span class="badge {{ $badges[$count->status] ?? 'text-bg-secondary' }}">
                                    {{ $statusLabels[$count->status] ?? $count->status }}
                                </span>
                            </td>
                            <td class="text-end">{{ $count->lines_count }}</td>
                            <td class="text-end">{{ number_format($variance, 2) }}</td>
                            <td>{{ $count->operator?->name ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('shop.counts.show', $count) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-eye"></i><span class="visually-hidden">查看 {{ $count->code }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">暂无盘点单</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $counts->links() }}
    </div>
@endsection
