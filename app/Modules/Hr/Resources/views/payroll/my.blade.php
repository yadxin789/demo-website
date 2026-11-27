@extends('hr::layouts.app')

@section('title', '我的工资条')
@section('sub', '华瑞集团（控股）· 只显示本人的工资明细（按工号匹配）')

@section('content')
    @if (! $latest)
        <div class="hr-card">
            <div class="hr-empty">
                还没有可查看的工资条。如果刚入职，等 HR 生成当月工资单后再来看。
            </div>
        </div>
    @else
        <div class="hr-card mb-3">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div>
                    <h3 style="margin:0 0 4px">最近一期 · {{ $runPeriods[$latest->run_id] ?? '—' }}</h3>
                    <div style="color:#6f6f80; font-size:13px">
                        工号 {{ $latest->employee_no }} ｜ {{ $latest->employee_name }}
                    </div>
                </div>
                <div class="text-end">
                    <div style="color:#9a9aad; font-size:12px">实发金额</div>
                    <div style="font-size:26px; font-weight:700; color:#5f3dc4">¥{{ number_format((float) $latest->net_pay, 2) }}</div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <table class="hr-table">
                        <thead><tr><th>收入项</th><th class="num">金额</th></tr></thead>
                        <tbody>
                        <tr><td>基本工资</td><td class="num">{{ number_format((float) $latest->base, 2) }}</td></tr>
                        <tr><td>岗位工资</td><td class="num">{{ number_format((float) $latest->post, 2) }}</td></tr>
                        <tr><td>绩效工资</td><td class="num">{{ number_format((float) $latest->performance, 2) }}</td></tr>
                        <tr><td>补贴</td><td class="num">{{ number_format((float) $latest->allowance, 2) }}</td></tr>
                        <tr><td>加班费</td><td class="num">{{ number_format((float) $latest->overtime_pay, 2) }}</td></tr>
                        <tr><td><b>应发合计</b></td><td class="num"><b>{{ number_format((float) $latest->gross, 2) }}</b></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="hr-table">
                        <thead><tr><th>扣减项</th><th class="num">金额</th></tr></thead>
                        <tbody>
                        <tr><td>请假扣款</td><td class="num" style="color:#d6336c">−{{ number_format((float) $latest->leave_deduct, 2) }}</td></tr>
                        <tr><td>社保（10.5%）</td><td class="num" style="color:#d6336c">−{{ number_format((float) $latest->social_insurance, 2) }}</td></tr>
                        <tr><td>个人所得税（3%）</td><td class="num" style="color:#d6336c">−{{ number_format((float) $latest->tax, 2) }}</td></tr>
                        <tr><td><b>扣除合计</b></td><td class="num" style="color:#d6336c">
                            <b>−{{ number_format((float) $latest->leave_deduct + (float) $latest->social_insurance + (float) $latest->tax, 2) }}</b>
                        </td></tr>
                        <tr><td><b>实发工资</b></td><td class="num"><b style="color:#5f3dc4">{{ number_format((float) $latest->net_pay, 2) }}</b></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="hr-card">
            <h3>历史工资条（近 {{ $items->count() }} 期）</h3>
            <table class="hr-table">
                <thead><tr><th>期间</th><th>工号</th><th class="num">应发</th><th class="num">扣减</th><th class="num">实发</th></tr></thead>
                <tbody>
                @foreach ($items as $i)
                    <tr>
                        <td>{{ $runPeriods[$i->run_id] ?? '—' }}</td>
                        <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $i->employee_no }}</td>
                        <td class="num">{{ number_format((float) $i->gross, 2) }}</td>
                        <td class="num" style="color:#d6336c">
                            −{{ number_format((float) $i->leave_deduct + (float) $i->social_insurance + (float) $i->tax, 2) }}
                        </td>
                        <td class="num" style="font-weight:600">{{ number_format((float) $i->net_pay, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="color:#9a9aad; font-size:12px; margin-top:8px">
                工资条按工号匹配，只查本人工号的数据；换账号登录看到的是另一个人的工资条。
            </div>
        </div>
    @endif
@endsection
