@extends('hr::layouts.app')

@section('title', '考勤汇总')
@section('sub', '按月聚合，只统计本库打卡记录')

@section('content')
    <div class="hr-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 style="margin:0">{{ $month }} 考勤汇总</h3>
            <form class="d-flex gap-2" method="get" action="{{ route('hr.reports.attendance') }}" style="margin:0">
                <input class="form-control form-control-sm" name="month" type="month" value="{{ $month }}" style="max-width:160px">
                <button class="btn btn-sm btn-outline-secondary" type="submit">切换月份</button>
            </form>
        </div>
    </div>

    <div class="hr-kpis mb-3">
        <div class="hr-kpi purple">
            <div class="lbl">打卡记录</div>
            <div class="num">{{ $kpi['total'] }}</div>
            <div class="hint">人次</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">正常率</div>
            <div class="num">{{ $kpi['rate'] }}%</div>
            <div class="hint">正常 {{ $kpi['normal'] }} 人次</div>
        </div>
        <div class="hr-kpi warn">
            <div class="lbl">迟到 / 早退</div>
            <div class="num">{{ $kpi['late'] }} / {{ $kpi['early'] }}</div>
            <div class="hint">按人次计</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">缺卡 / 缺勤</div>
            <div class="num">{{ $kpi['missing'] }}</div>
            <div class="hint">需异常处理跟进</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="hr-card">
                <h3>部门出勤</h3>
                <table class="hr-table">
                    <thead><tr><th>部门</th><th class="num">记录</th><th class="num">正常</th><th class="num">正常率</th><th class="num">迟到累计(分)</th></tr></thead>
                    <tbody>
                    @forelse ($byDept as $d)
                        <tr>
                            <td>{{ $d->dept ?? '未分配部门' }}</td>
                            <td class="num">{{ $d->total }}</td>
                            <td class="num">{{ $d->normal }}</td>
                            <td class="num" style="font-weight:600">
                                {{ $d->total > 0 ? round($d->normal / $d->total * 100, 1) : 0 }}%
                            </td>
                            <td class="num">{{ (int) $d->late_min }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="hr-empty">这个月没有打卡记录。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="hr-card">
                <h3>状态分布</h3>
                <table class="hr-table">
                    <thead><tr><th>状态</th><th class="num">人次</th></tr></thead>
                    <tbody>
                    @foreach ($statusText as $k => $v)
                        <tr><td>{{ $v }}</td><td class="num">{{ (int) ($statusCounts[$k] ?? 0) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="hr-card mt-3">
                <h3>异常工单</h3>
                <table class="hr-table">
                    <thead><tr><th>类型</th><th class="num">数量</th></tr></thead>
                    <tbody>
                    @forelse ($exceptions as $type => $n)
                        <tr><td>{{ $exceptionText[$type] ?? $type }}</td><td class="num">{{ $n }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="hr-empty">这个月没有异常工单。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hr-card mt-3">
                <h3>加班与请假</h3>
                <table class="hr-table">
                    <tbody>
                    <tr><td>已批准加班</td><td class="num"><b>{{ $kpi['overtime'] }}</b> 小时</td></tr>
                    <tr><td>已批准请假</td><td class="num"><b>{{ $kpi['leave'] }}</b> 天</td></tr>
                    </tbody>
                </table>
                <div style="font-size:12px; color:#9a9aad; margin-top:8px">
                    加班按 `overtimes` 的 approved 小时累加；请假按 `leaves` 的 approved 天数累加。
                </div>
            </div>
        </div>
    </div>
@endsection
