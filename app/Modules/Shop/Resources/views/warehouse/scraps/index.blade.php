@extends('shop::layouts.app')

@section('title', '报废单')
@section('heading', '报废单')

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'done' => '已报废', 'cancelled' => '已取消'];
        $badges = ['draft' => 'text-bg-secondary', 'done' => 'text-bg-success', 'cancelled' => 'text-bg-danger'];
        $lossLabels = ['expense' => '管理费用', 'other' => '营业外支出'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $scraps->total() }} 张报废单</p>
        <a href="{{ route('shop.scraps.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新建报废
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
                <caption id="scrap-table-caption" class="visually-hidden">报废单列表</caption>
                <thead>
                    <tr>
                        <th scope="col">单号</th>
                        <th scope="col">仓库</th>
                        <th scope="col">报废日期</th>
                        <th scope="col">损失类型</th>
                        <th scope="col">原因</th>
                        <th scope="col" class="text-end">行数</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($scraps as $scrap)
                        <tr>
                            <td>{{ $scrap->code }}</td>
                            <td>{{ $scrap->warehouse?->name ?? '—' }}</td>
                            <td>{{ optional($scrap->scrap_date)->format('Y-m-d') }}</td>
                            <td>{{ $lossLabels[$scrap->loss_type] ?? $scrap->loss_type }}</td>
                            <td>{{ $scrap->reason }}</td>
                            <td class="text-end">{{ $scrap->lines_count }}</td>
                            <td>
                                <span class="badge {{ $badges[$scrap->status] ?? 'text-bg-secondary' }}">
                                    {{ $statusLabels[$scrap->status] ?? $scrap->status }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.scraps.show', ['scrap' => $scrap->id]) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-eye"></i><span class="visually-hidden">查看 {{ $scrap->code }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暂无报废单</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $scraps->links() }}
    </div>
@endsection
