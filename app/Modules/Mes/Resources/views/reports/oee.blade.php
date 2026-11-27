@extends('mes::layouts.app')

@section('title', 'OEE 设备综合效率')
@section('sub', '可用率 × 性能 × 良率，按工作中心拆开看瓶颈在哪')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi {{ $all['oee'] >= 0.85 ? 'ok' : ($all['oee'] >= 0.65 ? 'orange' : 'bad') }}">
            <div class="lbl">全厂 OEE</div>
            <div class="num">{{ number_format($all['oee'] * 100, 1) }}<small>%</small></div>
            <div class="hint">世界级基准 85%</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">可用率 A</div>
            <div class="num">{{ number_format($all['a'] * 100, 1) }}<small>%</small></div>
            <div class="hint">运行 {{ number_format($totalRun, 0) }} 分 / 停机 {{ number_format($totalDown, 0) }} 分</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">性能 P</div>
            <div class="num">{{ number_format($all['p'] * 100, 1) }}<small>%</small></div>
            <div class="hint">实际节拍相对标准节拍的达成</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">良率 Q</div>
            <div class="num">{{ number_format($all['q'] * 100, 1) }}<small>%</small></div>
            <div class="hint">合格 {{ number_format($totalOk, 0) }} / 不良 {{ number_format($totalNg, 0) }}</div>
        </div>
    </div>

    <div class="mes-card">
        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" style="width:150px">
            <span style="color:#8a97a8">至</span>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" style="width:150px">
            <button class="btn btn-sm btn-primary">计算</button>
            <span class="mini" style="margin-left:8px">快捷：
                <a href="?from={{ now()->subDays(6)->toDateString() }}&to={{ now()->toDateString() }}">近 7 天</a> ·
                <a href="?from={{ now()->subDays(29)->toDateString() }}&to={{ now()->toDateString() }}">近 30 天</a>
            </span>
        </form>

        <table class="mes-table">
            <thead>
            <tr>
                <th>工作中心</th>
                <th class="num">运行（分）</th>
                <th class="num">停机（分）</th>
                <th class="num">合格</th>
                <th class="num">不良</th>
                <th class="num">A 可用率</th>
                <th class="num">P 性能</th>
                <th class="num">Q 良率</th>
                <th class="num">OEE</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                <tr style="{{ $r['active'] ? '' : 'opacity:.55' }}">
                    <td>{{ $r['code'] }} {{ $r['name'] }}</td>
                    <td class="num">{{ number_format($r['run'], 0) }}</td>
                    <td class="num" style="color:{{ $r['down'] > 0 ? '#c92a2a' : '#a8b3c1' }}">{{ number_format($r['down'], 0) }}</td>
                    <td class="num">{{ number_format($r['ok'], 0) }}</td>
                    <td class="num">{{ number_format($r['ng'], 0) }}</td>
                    <td class="num">{{ number_format($r['a'] * 100, 1) }}%</td>
                    <td class="num">{{ number_format($r['p'] * 100, 1) }}%</td>
                    <td class="num">{{ number_format($r['q'] * 100, 1) }}%</td>
                    <td class="num" style="font-weight:700; color:{{ $r['oee'] >= 0.85 ? '#2b8a3e' : ($r['oee'] >= 0.65 ? '#e8590c' : '#c92a2a') }}">
                        {{ number_format($r['oee'] * 100, 1) }}%
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="mes-empty">区间内没有报工，算不出 OEE。</div></td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mini" style="margin-top:10px">
            A = 运行 ÷ (运行 + 停机)；P = 理论工时 ÷ 运行工时（理论工时按工序标准工时折算，超产封顶 100%）；Q = 合格 ÷ (合格 + 不良)。灰行表示该中心这个区间没有开工。
        </div>
    </div>

    <div class="mes-card">
        <div style="font-weight:600; margin-bottom:12px">每日良率趋势（辅助看 Q 波动）</div>
        @if ($daily->isEmpty())
            <div class="mes-empty">区间内没有报工记录。</div>
        @else
            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>日期</th><th class="num">运行（分）</th><th class="num">合格</th><th class="num">不良</th><th class="num">良率</th></tr></thead>
                <tbody>
                @foreach ($daily as $d)
                    <tr>
                        <td>{{ $d['d'] }}</td>
                        <td class="num">{{ number_format($d['run'], 0) }}</td>
                        <td class="num">{{ number_format($d['ok'], 0) }}</td>
                        <td class="num">{{ number_format($d['ng'], 0) }}</td>
                        <td class="num">{{ number_format($d['q'] * 100, 1) }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
