@extends('hr::layouts.app')

@section('title', '人力成本')
@section('sub', '华瑞集团（控股）· 按工资批次看部门成本与结构')

@section('content')
    @if (! $run)
        <div class="hr-card"><div class="hr-empty">还没有工资批次，先去"工资批次"页生成一批。</div></div>
    @else
        <div class="hr-card mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 style="margin:0">{{ $period }} 成本 {{ $run->status === 'draft' ? '（草稿批次，数字仅供预览）' : '' }}</h3>
                <form class="d-flex gap-2" method="get" action="{{ route('hr.reports.payroll') }}" style="margin:0">
                    <select class="form-select form-select-sm" name="period" onchange="this.form.submit()" style="width:180px">
                        @foreach ($runs as $r)
                            <option value="{{ $r->period }}" @selected($r->period === $period)>{{ $r->period }} · {{ $r->payroll_no }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="hr-kpis mb-3">
            <div class="hr-kpi purple">
                <div class="lbl">应发合计</div>
                <div class="num" style="font-size:18px">¥{{ number_format($kpi['gross'], 2) }}</div>
                <div class="hint">{{ $kpi['people'] }} 人 · 人均 ¥{{ number_format($kpi['avg'], 2) }}</div>
            </div>
            <div class="hr-kpi ok">
                <div class="lbl">实发合计</div>
                <div class="num" style="font-size:18px">¥{{ number_format($kpi['net'], 2) }}</div>
                <div class="hint">扣完社保个税</div>
            </div>
            <div class="hr-kpi">
                <div class="lbl">社保率 / 个税率</div>
                <div class="num" style="font-size:18px">{{ $kpi['social_rate'] }}% / {{ $kpi['tax_rate'] }}%</div>
                <div class="hint">社保 ¥{{ number_format($kpi['social'], 2) }} · 个税 ¥{{ number_format($kpi['tax'], 2) }}</div>
            </div>
            <div class="hr-kpi {{ ($kpi['mom'] ?? 0) > 0 ? 'warn' : 'ok' }}">
                <div class="lbl">环比</div>
                <div class="num" style="font-size:18px">
                    {{ $kpi['mom'] === null ? '—' : ($kpi['mom'] > 0 ? '+' : '') . $kpi['mom'] . '%' }}
                </div>
                <div class="hint">对比 {{ $kpi['prev_period'] ?? '无上一批' }}</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="hr-card">
                    <h3>部门成本</h3>
                    <table class="hr-table">
                        <thead>
                        <tr><th>部门</th><th class="num">人数</th><th class="num">应发</th><th class="num">人均</th><th class="num">社保</th><th class="num">个税</th><th class="num">实发</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td>{{ $r['dept'] }}</td>
                                <td class="num">{{ $r['people'] }}</td>
                                <td class="num" style="font-weight:600">¥{{ number_format($r['gross'], 2) }}</td>
                                <td class="num">¥{{ number_format($r['avg'], 2) }}</td>
                                <td class="num">¥{{ number_format($r['social'], 2) }}</td>
                                <td class="num">¥{{ number_format($r['tax'], 2) }}</td>
                                <td class="num">¥{{ number_format($r['net'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="hr-empty">这批工资单没有明细。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="hr-card">
                    <h3>成本结构</h3>
                    @php($maxCost = max(1, max(array_column($costStructure, 'value'))))
                    @php($colors = ['#5f3dc4', '#4c6ef5', '#0ca678', '#d9480f', '#d6336c'])
                    <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px">
                        @foreach ($costStructure as $i => $c)
                            <div style="display:grid; grid-template-columns:96px 1fr 110px; align-items:center; gap:10px">
                                <span style="font-size:13px">{{ $c['label'] }}</span>
                                <div style="height:14px; background:#eceaf6; border-radius:7px; overflow:hidden">
                                    <span style="display:block; height:14px; background:{{ $colors[$i % 5] }}; width: {{ (int) round($c['value'] / $maxCost * 100) }}%"></span>
                                </div>
                                <b style="font-size:12px; text-align:right">¥{{ number_format($c['value'], 0) }}</b>
                            </div>
                        @endforeach
                    </div>
                    <div style="font-size:12px; color:#9a9aad; margin-top:10px">
                        社保按应发 10.5%、个税按简化 3% 口径，与工资单逐人明细一致。
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
