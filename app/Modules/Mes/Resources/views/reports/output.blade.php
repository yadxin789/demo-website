@extends('mes::layouts.app')

@section('title', '产量趋势')
@section('sub', '按天看合格产出，按产品和工单看结构')

@push('styles')
    <style>
        .bar-wrap { display: flex; align-items: flex-end; gap: 10px; height: 210px; padding: 8px 4px 0; }
        .bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; }
        .bar-col .v { font-size: 12px; color: #2b3a52; font-variant-numeric: tabular-nums; margin-bottom: 4px; }
        .bar-col .b { width: 100%; max-width: 54px; border-radius: 6px 6px 0 0; background: linear-gradient(180deg, #35c2f0, #1677b8); min-height: 2px; }
        .bar-col .b.zero { background: #dbe3ee; }
        .bar-col .d { font-size: 11px; color: #8a97a8; margin-top: 6px; font-family: ui-monospace, Consolas, monospace; }
        .legend { font-size: 12px; color: #8a97a8; }
        .mini { font-size: 12px; color: #8a97a8; }
    </style>
@endpush

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi ok">
            <div class="lbl">区间产量</div>
            <div class="num">{{ number_format($totalOk, 0) }}</div>
            <div class="hint">合格件 · {{ $days }} 天</div>
        </div>
        <div class="mes-kpi {{ $totalNg > 0 ? 'bad' : '' }}">
            <div class="lbl">区间不良</div>
            <div class="num">{{ number_format($totalNg, 0) }}</div>
            <div class="hint">件 · 不冲减产量</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">日均产量</div>
            <div class="num">{{ number_format($avgOk, 0) }}</div>
            <div class="hint">件 / 天</div>
        </div>
        <div class="mes-kpi orange">
            <div class="lbl">峰值日</div>
            <div class="num">{{ $peak ? number_format((float) $peak->ok, 0) : 0 }}</div>
            <div class="hint">{{ $peak ? $peak->d : '—' }}</div>
        </div>
    </div>

    <div class="mes-card">
        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" style="width:150px">
            <span style="color:#8a97a8">至</span>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" style="width:150px">
            <button class="btn btn-sm btn-primary">看趋势</button>
            <span class="legend" style="margin-left:8px">快捷：
                <a href="?from={{ now()->subDays(6)->toDateString() }}&to={{ now()->toDateString() }}">近 7 天</a> ·
                <a href="?from={{ now()->subDays(13)->toDateString() }}&to={{ now()->toDateString() }}">近 14 天</a> ·
                <a href="?from={{ now()->subDays(29)->toDateString() }}&to={{ now()->toDateString() }}">近 30 天</a>
            </span>
        </form>

        <div class="legend" style="margin-bottom:4px">每日合格产出（柱高按区间峰值归一化）</div>
        @if ($byDate->isEmpty())
            <div class="mes-empty">这个区间没有报工记录，换个日期试试。</div>
        @else
            <div class="bar-wrap">
                @foreach ($byDate as $row)
                    @php $h = max(2, (int) round((float) $row->ok / $maxOk * 100)); @endphp
                    <div class="bar-col">
                        <div class="v">{{ number_format((float) $row->ok, 0) }}</div>
                        <div class="b {{ (float) $row->ok == 0 ? 'zero' : '' }}" style="height: {{ $h }}%"
                             title="{{ $row->d }} · 合格 {{ number_format((float) $row->ok, 0) }} / 不良 {{ number_format((float) $row->ng, 0) }} · {{ $row->reports }} 笔报工"></div>
                        <div class="d">{{ substr($row->d, 5) }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="wo-grid">
        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">按产品</div>
            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>产品</th><th class="num">合格</th><th class="num">不良</th><th class="num">良率</th></tr></thead>
                <tbody>
                @forelse ($byProduct as $p)
                    @php $t = (float) $p->ok + (float) $p->ng; @endphp
                    <tr>
                        <td><span style="font-family:ui-monospace,Consolas,monospace; font-size:12px; color:#6b7a90">{{ $p->code }}</span> {{ $p->name }}</td>
                        <td class="num">{{ number_format((float) $p->ok, 0) }}</td>
                        <td class="num" style="color:{{ (float) $p->ng > 0 ? '#c92a2a' : '#a8b3c1' }}">{{ number_format((float) $p->ng, 0) }}</td>
                        <td class="num">{{ $t > 0 ? round((float) $p->ok / $t * 100, 1) : '—' }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="mes-empty">区间内没有产出。</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">按工单（前 10）</div>
            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>工单</th><th class="num">计划</th><th class="num">区间合格</th><th class="num">不良</th></tr></thead>
                <tbody>
                @forelse ($byOrder as $o)
                    <tr>
                        <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $o->order_no }}</td>
                        <td class="num">{{ number_format((float) $o->plan_qty, 0) }}</td>
                        <td class="num">{{ number_format((float) $o->ok, 0) }}</td>
                        <td class="num" style="color:{{ (float) $o->ng > 0 ? '#c92a2a' : '#a8b3c1' }}">{{ number_format((float) $o->ng, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="mes-empty">区间内没有产出。</div></td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="mini" style="margin-top:10px">口径说明：产量按报工合格数累计，工单维度的完成进度请看工单详情（取瓶颈工序）。</div>
        </div>
    </div>
@endsection
