@extends('mes::layouts.app')

@section('title', '工时效率')
@section('sub', '标准工时 ÷ 实际工时，按人 / 工作中心 / 班次三种切面')

@push('styles')
    <style>
        .mini { font-size: 12px; color: #8a97a8; }
        .eff { display: flex; align-items: center; gap: 8px; }
        .eff .track { width: 72px; height: 10px; background: #eef2f8; border-radius: 6px; overflow: hidden; }
        .eff .fill { height: 100%; background: linear-gradient(90deg, #37d67a, #1677b8); }
    </style>
@endpush

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi ok">
            <div class="lbl">区间产出</div>
            <div class="num">{{ number_format($totalOk, 0) }}</div>
            <div class="hint">合格件</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">实际工时</div>
            <div class="num">{{ number_format($actMinutes, 0) }}</div>
            <div class="hint">分钟 · {{ number_format($actMinutes / 60, 1) }} 小时</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">标准工时</div>
            <div class="num">{{ number_format($stdMinutes, 0) }}</div>
            <div class="hint">分钟 · Σ(报工数量 × 工序标准分钟)</div>
        </div>
        <div class="mes-kpi {{ $efficiency === null ? '' : ($efficiency >= 90 ? 'ok' : ($efficiency >= 70 ? 'orange' : 'bad')) }}">
            <div class="lbl">工时效率</div>
            <div class="num">{{ $efficiency !== null ? number_format($efficiency, 1) : '—' }}<small>%</small></div>
            <div class="hint">标准 ÷ 实际 · 越高越省时</div>
        </div>
    </div>

    <div class="mes-card">
        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" style="width:150px">
            <span style="color:#8a97a8">至</span>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" style="width:150px">
            <button class="btn btn-sm btn-primary">看效率</button>
            <span class="mini" style="margin-left:8px">快捷：
                <a href="?from={{ now()->subDays(6)->toDateString() }}&to={{ now()->toDateString() }}">近 7 天</a> ·
                <a href="?from={{ now()->subDays(13)->toDateString() }}&to={{ now()->toDateString() }}">近 14 天</a> ·
                <a href="?from={{ now()->subDays(29)->toDateString() }}&to={{ now()->toDateString() }}">近 30 天</a>
            </span>
        </form>
        <div class="mini">口径：实际工时取报工填写的分钟数；标准工时按「报工数量（含不良）×工序标准分钟」累加；产出效率 = 合格件 ÷ 小时。</div>
    </div>

    <div class="mes-card">
        <div style="font-weight:600; margin-bottom:10px">按人</div>
        <table class="mes-table" style="font-size:13px">
            <thead><tr><th>报工人</th><th class="num">笔数</th><th class="num">合格</th><th class="num">不良</th><th class="num">良率</th><th class="num">工时(分)</th><th class="num">件/小时</th></tr></thead>
            <tbody>
            @forelse ($byUser as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td class="num">{{ $u->reports }}</td>
                    <td class="num">{{ number_format((float) $u->ok, 0) }}</td>
                    <td class="num" style="color:{{ (float) $u->ng > 0 ? '#c92a2a' : '#a8b3c1' }}">{{ number_format((float) $u->ng, 0) }}</td>
                    <td class="num">{{ $u->yield !== null ? number_format($u->yield, 1).'%' : '—' }}</td>
                    <td class="num">{{ number_format((int) $u->minutes) }}</td>
                    <td class="num"><b>{{ $u->per_hour !== null ? number_format($u->per_hour, 1) : '—' }}</b></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="mes-empty">区间内没有报工。</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="wo-grid">
        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">按工作中心</div>
            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>工作中心</th><th class="num">合格</th><th class="num">实际(分)</th><th class="num">标准(分)</th><th>效率</th></tr></thead>
                <tbody>
                @forelse ($byCenter as $c)
                    <tr>
                        <td>{{ $c->name }}<span style="font-size:11px; color:#8a97a8"> {{ $c->code }}</span></td>
                        <td class="num">{{ number_format((float) $c->ok, 0) }}</td>
                        <td class="num">{{ number_format((int) $c->minutes) }}</td>
                        <td class="num">{{ number_format((float) $c->std_minutes, 0) }}</td>
                        <td>
                            @if ($c->efficiency !== null)
                                <div class="eff">
                                    <div class="track"><div class="fill" style="width: {{ min(100, max(2, $c->efficiency)) }}%"></div></div>
                                    <span style="font-size:12px">{{ number_format($c->efficiency, 1) }}%</span>
                                </div>
                            @else
                                <span style="color:#a8b3c1">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="mes-empty">区间内没有报工。</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">按班次</div>
            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>班次</th><th class="num">笔数</th><th class="num">合格</th><th class="num">工时(分)</th><th class="num">件/小时</th></tr></thead>
                <tbody>
                @forelse ($byShift as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td class="num">{{ $s->reports }}</td>
                        <td class="num">{{ number_format((float) $s->ok, 0) }}</td>
                        <td class="num">{{ number_format((int) $s->minutes) }}</td>
                        <td class="num">{{ $s->per_hour !== null ? number_format($s->per_hour, 1) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="mes-empty">区间内没有报工。</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
