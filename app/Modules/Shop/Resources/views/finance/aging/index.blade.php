@extends('shop::layouts.app')

@section('title', '应收应付账龄表')
@section('heading', '往来账龄表')

@section('content')
    <form method="get" action="{{ route('shop.aging.index') }}" class="row g-2 mb-3 align-items-end" aria-label="账龄筛选">
        <div class="col-12 col-md-3">
            <label for="type" class="form-label">往来类型</label>
            <select id="type" name="type" class="form-select" onchange="this.form.submit()">
                <option value="receivable" @selected($type === 'receivable')>应收（客户）</option>
                <option value="payable" @selected($type === 'payable')>应付（供应商）</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="partner_id" class="form-label">{{ $type === 'payable' ? '供应商' : '客户' }}</label>
            <select id="partner_id" name="partner_id" class="form-select">
                <option value="">全部</option>
                @foreach ($partners as $partner)
                    <option value="{{ $partner->id }}" @selected($partnerId === $partner->id)>
                        {{ $partner->code }} · {{ $partner->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label for="as_of" class="form-label">基准日</label>
            <input type="date" id="as_of" name="as_of" value="{{ $asOf }}" class="form-control">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>查询</button>
            <a href="{{ route('shop.aging.index', ['type' => $type]) }}" class="btn btn-outline-secondary">重置</a>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">{{ $type === 'payable' ? '应付余额合计' : '应收余额合计' }}</div>
                <div class="fs-5 fw-bold">{{ number_format($total, 4) }}</div>
            </div>
        </div>
    </div>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">往来账龄分段清单</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ $type === 'payable' ? '供应商' : '客户' }}</th>
                        @foreach ($buckets as $bucket)
                            <th scope="col" class="text-end">{{ $labels[$bucket] }}</th>
                        @endforeach
                        <th scope="col" class="text-end">余额合计</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['partner_code'] }} · {{ $row['partner_name'] }}</td>
                            @foreach ($buckets as $bucket)
                                <td class="text-end">{{ number_format($row['buckets'][$bucket], 4) }}</td>
                            @endforeach
                            <td class="text-end fw-bold">{{ number_format($row['balance'], 4) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($buckets) + 2 }}" class="text-center text-muted py-4">所选条件下暂无未结往来</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th scope="row" class="text-end">合计</th>
                            @foreach ($buckets as $bucket)
                                <th class="text-end">{{ number_format($totals[$bucket], 4) }}</th>
                            @endforeach
                            <th class="text-end">{{ number_format($total, 4) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
