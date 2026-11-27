@extends('mes::layouts.app')

@section('title', '批次 ' . $lot->lot_no)
@section('sub', '上游组成 → 本批 → 下游流向')

@section('content')
    <div class="mes-card">
        <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap">
            <div>
                <div class="mini">批次号</div>
                <div style="font-size:22px; font-weight:700; font-family:ui-monospace,Consolas,monospace">{{ $lot->lot_no }}</div>
            </div>
            <div>
                <div class="mini">物料</div>
                <div>{{ $lot->material?->code }} {{ $lot->material?->name }}</div>
            </div>
            <div>
                <div class="mini">数量</div>
                <div>{{ rtrim(rtrim(number_format((float) $lot->qty, 3), '0'), '.') }} {{ $lot->material?->unit }}</div>
            </div>
            <div>
                <div class="mini">来源</div>
                <div>{{ $lot->source === 'production' ? '生产（工单完工）' : ($lot->source === 'purchase' ? '采购入库' : '其它') }}</div>
            </div>
            <div>
                <div class="mini">生产日期</div>
                <div>{{ $lot->produced_at?->toDateString() ?? '—' }}</div>
            </div>
            <div>
                <div class="mini">关联工单</div>
                <div>
                    @if ($order)
                        <a href="{{ route('mes.work-orders.show', $order) }}">{{ $order->order_no }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <a class="btn btn-sm btn-light" style="margin-left:auto" href="{{ route('mes.trace.index') }}">返回追溯列表</a>
        </div>
    </div>

    <div class="wo-grid">
        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">上游（这批由哪些料做成）</div>
            @if (count($upstream) === 0 && ! $lot->parent_lot_no)
                <div class="mes-empty">没有记录上游批次。入库时如果领料台账里有对应原料批次，会自动挂上。</div>
            @else
                <table class="mes-table" style="font-size:13px">
                    <thead><tr><th>层级</th><th>批次号</th><th>物料</th><th class="num">数量</th></tr></thead>
                    <tbody>
                    @foreach ($upstream as $i => $up)
                        <tr>
                            <td>L{{ count($upstream) - $i }}</td>
                            <td><a href="{{ route('mes.trace.show', $up->lot_no) }}">{{ $up->lot_no }}</a></td>
                            <td>{{ $up->material?->code }} {{ $up->material?->name }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format((float) $up->qty, 3), '0'), '.') }}</td>
                        </tr>
                    @endforeach
                    @if ($lot->parent_lot_no && count($upstream) === 0)
                        <tr><td>L1</td><td>{{ $lot->parent_lot_no }}</td><td>—</td><td class="num">—</td></tr>
                    @endif
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">下游（这批料做成了什么）</div>
            @if ($downstream->isEmpty())
                <div class="mes-empty">没有下游批次（原料还没被投产，或成品批次未挂这批料）。</div>
            @else
                <table class="mes-table" style="font-size:13px">
                    <thead><tr><th>批次号</th><th>物料</th><th>工单</th><th class="num">数量</th></tr></thead>
                    <tbody>
                    @foreach ($downstream as $d)
                        <tr>
                            <td><a href="{{ route('mes.trace.show', $d->lot_no) }}">{{ $d->lot_no }}</a></td>
                            <td>{{ $d->material?->code }} {{ $d->material?->name }}</td>
                            <td>{{ $d->workOrder?->order_no ?? '—' }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format((float) $d->qty, 3), '0'), '.') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="mes-card">
        <div style="font-weight:600; margin-bottom:10px">入库记录</div>
        @if ($receipts->isEmpty())
            <div class="mes-empty">这个批次没有关联的完工入库单（可能是原料批次）。</div>
        @else
            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>入库单</th><th>工单</th><th class="num">数量</th><th>仓库</th><th>时间</th></tr></thead>
                <tbody>
                @foreach ($receipts as $r)
                    <tr>
                        <td>{{ $r->receipt_no }}</td>
                        <td>{{ $r->workOrder?->order_no ?? '—' }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $r->qty, 3), '0'), '.') }}</td>
                        <td>{{ $r->warehouse }}</td>
                        <td>{{ $r->received_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
