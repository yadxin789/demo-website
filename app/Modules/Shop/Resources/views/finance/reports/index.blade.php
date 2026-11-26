@extends('shop::layouts.app')

@section('title', '财务报表')
@section('heading', '财务报表（初稿）')

@section('content')
    @php
        $tabs = [
            'balance' => '资产负债表',
            'income' => '利润表',
            'cash' => '现金流量表',
        ];
    @endphp

    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>本报表由已过账凭证自动汇总，属于初稿；未经会计复核不得作为对外报表或纳税申报依据。</div>
    </div>

    <div class="d-flex gap-2 mb-3" role="group" aria-label="报表切换">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('shop.financial-reports.index', ['tab' => $key, 'start' => $start, 'end' => $end]) }}"
               class="btn {{ $tab === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>

    <form method="get" action="{{ route('shop.financial-reports.index') }}" class="row g-2 mb-3 align-items-end">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="col-12 col-md-3">
            <label for="start" class="form-label">起始日期</label>
            <input type="date" id="start" name="start" value="{{ $start }}" class="form-control">
        </div>
        <div class="col-12 col-md-3">
            <label for="end" class="form-label">{{ $tab === 'balance' ? '截止日期' : '截止日期' }}</label>
            <input type="date" id="end" name="end" value="{{ $end }}" class="form-control">
        </div>
        <div class="col-12 col-md-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>查询</button>
        </div>
    </form>

    @if ($tab === 'balance')
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card p-3"><div class="text-muted small">资产合计</div><div class="fs-5 fw-bold">{{ number_format($balance['totals']['asset'], 4) }}</div></div></div>
            <div class="col-md-3"><div class="card p-3"><div class="text-muted small">负债合计</div><div class="fs-5 fw-bold">{{ number_format($balance['totals']['liability'], 4) }}</div></div></div>
            <div class="col-md-3"><div class="card p-3"><div class="text-muted small">所有者权益合计</div><div class="fs-5 fw-bold">{{ number_format($balance['totals']['equity'], 4) }}</div></div></div>
            <div class="col-md-3"><div class="card p-3"><div class="text-muted small">平衡校验</div><div class="fs-5 fw-bold {{ $balance['balanced'] ? 'text-success' : 'text-danger' }}">{{ $balance['balanced'] ? '平衡' : '不平衡' }}</div></div></div>
        </div>
        <div class="card p-0">
            <div class="table-wrapper">
                <table class="table table-hover mb-0">
                    <thead><tr><th>科目编码</th><th>科目名称</th><th>类别</th><th class="text-end">金额</th></tr></thead>
                    <tbody>
                        @forelse ($balance['rows'] as $row)
                            <tr>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['type_label'] }}</td>
                                <td class="text-end">{{ number_format($row['amount'], 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">截至 {{ $balance['as_of'] }} 暂无资产、负债或权益余额</td></tr>
                        @endforelse
                    </tbody>
                    @if ($balance['rows'] !== [])
                        <tfoot><tr><th colspan="3" class="text-end">资产合计 / 负债+权益合计</th><th class="text-end">{{ number_format($balance['totals']['asset'], 4) }} / {{ number_format($balance['right_total'], 4) }}</th></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>
    @elseif ($tab === 'income')
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card p-3"><div class="text-muted small">收入合计</div><div class="fs-5 fw-bold">{{ number_format($income['income'], 4) }}</div></div></div>
            <div class="col-md-4"><div class="card p-3"><div class="text-muted small">费用合计</div><div class="fs-5 fw-bold">{{ number_format($income['expense'], 4) }}</div></div></div>
            <div class="col-md-4"><div class="card p-3"><div class="text-muted small">本期利润</div><div class="fs-5 fw-bold {{ $income['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($income['profit'], 4) }}</div></div></div>
        </div>
        <div class="card p-0">
            <div class="table-wrapper">
                <table class="table table-hover mb-0">
                    <thead><tr><th>科目编码</th><th>科目名称</th><th>类别</th><th class="text-end">本期发生额</th></tr></thead>
                    <tbody>
                        @forelse ($income['rows'] as $row)
                            <tr>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['type_label'] }}</td>
                                <td class="text-end">{{ number_format($row['amount'], 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">{{ $income['start'] }} 至 {{ $income['end'] }} 暂无损益发生额</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card p-3"><div class="text-muted small">现金流入</div><div class="fs-5 fw-bold text-success">{{ number_format($cash['inflow'], 4) }}</div></div></div>
            <div class="col-md-4"><div class="card p-3"><div class="text-muted small">现金流出</div><div class="fs-5 fw-bold text-danger">{{ number_format($cash['outflow'], 4) }}</div></div></div>
            <div class="col-md-4"><div class="card p-3"><div class="text-muted small">净现金流</div><div class="fs-5 fw-bold">{{ number_format($cash['net'], 4) }}</div></div></div>
        </div>
        <div class="card p-0">
            <div class="table-wrapper">
                <table class="table table-hover mb-0">
                    <thead><tr><th>日期</th><th>凭证号</th><th>摘要</th><th>活动类别</th><th class="text-end">净现金流</th></tr></thead>
                    <tbody>
                        @forelse ($cash['rows'] as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['description'] }}</td>
                                <td>{{ $row['category'] }}</td>
                                <td class="text-end {{ $row['amount'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($row['amount'], 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ $cash['start'] }} 至 {{ $cash['end'] }} 暂无资金流水</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
