@extends('shop::layouts.app')

@section('title', '税费归集')
@section('heading', '税费归集与税表底表')

@section('content')
    <form method="get" action="{{ route('shop.tax-reports.index') }}" class="row g-2 mb-3 align-items-end" aria-label="税费期间筛选">
        <div class="col-12 col-md-2">
            <label for="start" class="form-label">起始日期</label>
            <input type="date" id="start" name="start" value="{{ $start }}" class="form-control">
        </div>
        <div class="col-12 col-md-2">
            <label for="end" class="form-label">截止日期</label>
            <input type="date" id="end" name="end" value="{{ $end }}" class="form-control">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>查询</button>
            <a href="{{ route('shop.tax-reports.index') }}" class="btn btn-outline-secondary">重置</a>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">销项税额合计（2004 贷方）</div>
                <div class="fs-5 fw-bold">{{ number_format($outputTotal, 4) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">进项税额合计（2003 借方）</div>
                <div class="fs-5 fw-bold">{{ number_format($inputTotal, 4) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">本期应交增值税（销项 − 进项）</div>
                <div class="fs-5 fw-bold {{ $payable >= 0 ? 'text-danger' : 'text-success' }}">
                    {{ number_format($payable, 4) }}
                </div>
            </div>
        </div>
    </div>

    <div class="card p-0 mb-3">
        <div class="card-header">销项税额底表</div>
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">销项税额归集明细</caption>
                <thead>
                    <tr>
                        <th scope="col">税码</th>
                        <th scope="col">税种名称</th>
                        <th scope="col" class="text-end">税率</th>
                        <th scope="col" class="text-end">税额</th>
                        <th scope="col" class="text-end">分录笔数</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outputLines as $row)
                        <tr>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-end">{{ $row['rate'] !== null ? number_format($row['rate'], 2) . '%' : '—' }}</td>
                            <td class="text-end">{{ number_format($row['amount'], 4) }}</td>
                            <td class="text-end">{{ $row['count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">本期无销项税额分录</td></tr>
                    @endforelse
                </tbody>
                @if ($outputLines->isNotEmpty())
                    <tfoot>
                        <tr><th colspan="3" scope="row" class="text-end">销项合计</th><th class="text-end">{{ number_format($outputTotal, 4) }}</th><th></th></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="card p-0">
        <div class="card-header">进项税额底表</div>
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">进项税额归集明细</caption>
                <thead>
                    <tr>
                        <th scope="col">税码</th>
                        <th scope="col">税种名称</th>
                        <th scope="col" class="text-end">税率</th>
                        <th scope="col" class="text-end">税额</th>
                        <th scope="col" class="text-end">分录笔数</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inputLines as $row)
                        <tr>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-end">{{ $row['rate'] !== null ? number_format($row['rate'], 2) . '%' : '—' }}</td>
                            <td class="text-end">{{ number_format($row['amount'], 4) }}</td>
                            <td class="text-end">{{ $row['count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">本期无进项税额分录</td></tr>
                    @endforelse
                </tbody>
                @if ($inputLines->isNotEmpty())
                    <tfoot>
                        <tr><th colspan="3" scope="row" class="text-end">进项合计</th><th class="text-end">{{ number_format($inputTotal, 4) }}</th><th></th></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
