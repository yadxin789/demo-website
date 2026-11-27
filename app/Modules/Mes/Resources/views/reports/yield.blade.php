@extends('mes::layouts.app')

@section('title', '良率分析')
@section('sub', '良率趋势 + 不良 Top5 + 检验通过率')

@push('styles')
    <style>
        .chart { width: 100%; height: 300px; display: block; }
        .chart .grid { stroke: #e3ebf5; stroke-width: 1; }
        .chart .axis { stroke: #cfd9e6; stroke-width: 1.5; }
        .chart .line { fill: none; stroke: #1677b8; stroke-width: 3; stroke-linejoin: round; }
        .chart .dot { fill: #35c2f0; stroke: #fff; stroke-width: 2; }
        .chart text { font-size: 11px; fill: #8a97a8; font-family: ui-monospace, Consolas, monospace; }
        .dbar { display: flex; align-items: center; gap: 10px; margin-bottom: 9px; }
        .dbar .nm { width: 108px; font-size: 13px; }
        .dbar .track { flex: 1; height: 14px; background: #eef2f8; border-radius: 8px; overflow: hidden; }
        .dbar .fill { height: 100%; background: linear-gradient(90deg, #ff8a2b, #ff5b5b); border-radius: 8px; }
        .dbar .v { width: 74px; text-align: right; font-size: 13px; font-variant-numeric: tabular-nums; color: #4a5568; }
        .mini { font-size: 12px; color: #8a97a8; }
    </style>
@endpush

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi {{ $overall >= 97 ? 'ok' : ($overall >= 93 ? 'orange' : 'bad') }}">
            <div class="lbl">区间良率</div>
            <div class="num">{{ number_format($overall, 1) }}<small>%</small></div>
            <div class="hint">合格 {{ number_format($totalOk, 0) }} / 不良 {{ number_format($totalNg, 0) }}</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">最好的一天</div>
            <div class="num">{{ $best ? number_format((float) $best->yield, 1) : '—' }}<small>%</small></div>
            <div class="hint">{{ $best ? $best->d : '区间内无报工' }}</div>
        </div>
        <div class="mes-kpi bad">
            <div class="lbl">最差的一天</div>
            <div class="num">{{ $worst ? number_format((float) $worst->yield, 1) : '—' }}<small>%</small></div>
            <div class="hint">{{ $worst ? $worst->d : '区间内无报工' }}</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">不良记录</div>
            <div class="num">{{ number_format((float) $topDefects->sum('qty'), 0) }}<small>件</small></div>
            <div class="hint">Top5 合计 · {{ $topDefects->sum('cases') }} 条</div>
        </div>
    </div>

    <div class="mes-card">
        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" style="width:150px">
            <span style="color:#8a97a8">至</span>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" style="width:150px">
            <button class="btn btn-sm btn-primary">看良率</button>
            <span class="mini" style="margin-left:8px">快捷：
                <a href="?from={{ now()->subDays(6)->toDateString() }}&to={{ now()->toDateString() }}">近 7 天</a> ·
                <a href="?from={{ now()->subDays(13)->toDateString() }}&to={{ now()->toDateString() }}">近 14 天</a> ·
                <a href="?from={{ now()->subDays(29)->toDateString() }}&to={{ now()->toDateString() }}">近 30 天</a>
            </span>
        </form>

        <div class="mini" style="margin-bottom:6px">每日良率 = 合格 ÷ (合格 + 不良)，与看板 / 工作台同一口径</div>
        <svg class="chart" viewBox="0 0 1000 300" preserveAspectRatio="none">
            <line class="grid" x1="20" y1="20" x2="980" y2="20"></line>
            <line class="grid" x1="20" y1="150" x2="980" y2="150"></line>
            <line class="axis" x1="20" y1="280" x2="980" y2="280"></line>
            <text x="24" y="16">100%</text>
            <text x="24" y="146">50%</text>
            <text x="24" y="296">0%</text>
            @if ($points !== '')
                <polyline class="line" points="{{ $points }}"></polyline>
                @foreach (explode(' ', $points) as $pt)
                    @php [$px, $py] = array_pad(explode(',', $pt), 2, '0'); @endphp
                    <circle class="dot" cx="{{ $px }}" cy="{{ $py }}" r="4"></circle>
                @endforeach
            @endif
        </svg>
        @if ($byDate->isEmpty())
            <div class="mes-empty">这个区间没有报工记录。</div>
        @endif
        <div class="mini" style="margin-top:6px">
            @foreach ($byDate as $row)
                <span style="margin-right:10px">{{ substr($row->d, 5) }}: <b>{{ $row->yield !== null ? number_format((float) $row->yield, 1).'%' : '无' }}</b></span>
            @endforeach
        </div>
    </div>

    <div class="wo-grid">
        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:12px">不良 Top5（按数量）</div>
            @forelse ($topDefects as $d)
                <div class="dbar">
                    <div class="nm">{{ $d->label }}</div>
                    <div class="track"><div class="fill" style="width: {{ max(2, round((float) $d->qty / $maxDefectQty * 100)) }}%"></div></div>
                    <div class="v">{{ number_format((float) $d->qty, 0) }} 件 / {{ $d->cases }} 条</div>
                </div>
            @empty
                <div class="mes-empty">区间内没有不良记录，保持住。</div>
            @endforelse
        </div>

        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">检验通过率</div>
            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>类型</th><th class="num">合格</th><th class="num">不合格</th><th class="num">待检</th><th class="num">通过率</th></tr></thead>
                <tbody>
                @forelse ($inspectionRate as $r)
                    <tr>
                        <td><span class="mes-badge b-released">{{ $r->type }}</span></td>
                        <td class="num">{{ $r->pass }}</td>
                        <td class="num" style="color:{{ (int) $r->fail > 0 ? '#c92a2a' : '#a8b3c1' }}">{{ $r->fail }}</td>
                        <td class="num" style="color:{{ (int) $r->pending > 0 ? '#ff8a2b' : '#a8b3c1' }}">{{ $r->pending }}</td>
                        <td class="num">{{ $r->pct !== null ? number_format((float) $r->pct, 1).'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="mes-empty">区间内没有检验单。</div></td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="mini" style="margin-top:10px">通过率只统计已判定的单（pass / fail），待检单不计入分母。</div>
        </div>
    </div>
@endsection
