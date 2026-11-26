@extends('shop::layouts.app')

@section('title', "报废单 {$scrap->code}")
@section('heading', "报废单 {$scrap->code}")

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'done' => '已报废', 'cancelled' => '已取消'];
        $badges = ['draft' => 'text-bg-secondary', 'done' => 'text-bg-success', 'cancelled' => 'text-bg-danger'];
        $lossLabels = ['expense' => '管理费用', 'other' => '营业外支出'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('shop.scraps.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>返回列表
        </a>
        @if ($scrap->status === \App\Modules\Shop\Models\Scrap::STATUS_DRAFT)
            <form method="post" action="{{ route('shop.scraps.post', ['scrap' => $scrap->id]) }}"
                  onsubmit="return confirm('确认过账报废单？过账后将扣减库存并结转损失，不能撤销。');">
                @csrf
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-check me-1"></i>过账报废</button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <dl class="row mb-0">
                <dt class="col-sm-4">单号</dt>
                <dd class="col-sm-8">{{ $scrap->code }}</dd>
                <dt class="col-sm-4">仓库</dt>
                <dd class="col-sm-8">{{ $scrap->warehouse?->name ?? '—' }}</dd>
                <dt class="col-sm-4">报废日期</dt>
                <dd class="col-sm-8">{{ optional($scrap->scrap_date)->format('Y-m-d') }}</dd>
                <dt class="col-sm-4">损失类型</dt>
                <dd class="col-sm-8">{{ $lossLabels[$scrap->loss_type] ?? $scrap->loss_type }}</dd>
            </dl>
        </div>
        <div class="col-md-6">
            <dl class="row mb-0">
                <dt class="col-sm-4">状态</dt>
                <dd class="col-sm-8">
                    <span class="badge {{ $badges[$scrap->status] ?? 'text-bg-secondary' }}">
                        {{ $statusLabels[$scrap->status] ?? $scrap->status }}
                    </span>
                </dd>
                <dt class="col-sm-4">操作员</dt>
                <dd class="col-sm-8">{{ $scrap->operator?->name ?? '—' }}</dd>
                <dt class="col-sm-4">报废原因</dt>
                <dd class="col-sm-8">{{ $scrap->reason }}</dd>
                <dt class="col-sm-4">备注</dt>
                <dd class="col-sm-8">{{ $scrap->note ?: '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">报废明细行</caption>
                <thead>
                    <tr>
                        <th scope="col">商品</th>
                        <th scope="col">库位</th>
                        <th scope="col">批次号</th>
                        <th scope="col" class="text-end">数量</th>
                        <th scope="col" class="text-end">标准成本</th>
                        <th scope="col" class="text-end">损失金额</th>
                        <th scope="col">行原因</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scrap->lines as $line)
                        <tr>
                            <td>{{ $line->product?->code }} · {{ $line->product?->name }}</td>
                            <td>{{ $line->location?->name ?? '默认' }}</td>
                            <td>{{ $line->lot_no ?: '—' }}</td>
                            <td class="text-end">{{ number_format((float) $line->qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $line->unit_cost, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $line->loss_amount, 4) }}</td>
                            <td>{{ $line->reason ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" scope="row" class="text-end">损失合计</th>
                        <th class="text-end">{{ number_format($scrap->lines->sum('loss_amount'), 4) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
