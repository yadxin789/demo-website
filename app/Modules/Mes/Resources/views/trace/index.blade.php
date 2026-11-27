@extends('mes::layouts.app')

@section('title', '批次追溯')
@section('sub', '输入批次号 / 工单号 / 物料，查看这批货用了什么料、又做成了什么')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">批次总数</div>
            <div class="num">{{ $stat['total'] }}<small>批</small></div>
            <div class="hint">含原料批次与成品批次</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">成品批次</div>
            <div class="num">{{ $stat['production'] }}<small>批</small></div>
            <div class="hint">完工入库生成</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">已挂上游</div>
            <div class="num">{{ $stat['linked'] }}<small>批</small></div>
            <div class="hint">可正向追到原料</div>
        </div>
    </div>

    <div class="mes-card">
        <form method="get" style="display:flex; gap:8px; align-items:center; margin-bottom:12px">
            <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:320px" placeholder="试：LOT-2026… / WO-2026-0001 / 外壳">
            <button class="btn btn-sm btn-primary">追溯</button>
            <a class="btn btn-sm btn-light" href="{{ route('mes.trace.index') }}">清空</a>
        </form>

        <table class="mes-table">
            <thead>
            <tr><th>批次号</th><th>物料</th><th>来源</th><th>关联工单</th><th class="num">数量</th><th>上游批次</th><th>生产日期</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($lots as $l)
                <tr>
                    <td><a href="{{ route('mes.trace.show', $l->lot_no) }}"><b>{{ $l->lot_no }}</b></a></td>
                    <td>{{ $l->material?->code }} {{ $l->material?->name }}</td>
                    <td>
                        <span class="mes-badge b-{{ $l->source === 'production' ? 'released' : 'draft' }}">
                            {{ $l->source === 'production' ? '生产' : ($l->source === 'purchase' ? '采购' : '其它') }}
                        </span>
                    </td>
                    <td>{{ $l->workOrder?->order_no ?? '—' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $l->qty, 3), '0'), '.') }}</td>
                    <td>{{ $l->parent_lot_no ?? '—' }}</td>
                    <td>{{ $l->produced_at?->toDateString() ?? '—' }}</td>
                    <td><a class="btn btn-sm btn-light" href="{{ route('mes.trace.show', $l->lot_no) }}">查上下游</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="mes-empty">没有匹配的批次。工单完工入库后会自动生成批次记录。</div></td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:12px">{{ $lots->links() }}</div>
    </div>
@endsection
