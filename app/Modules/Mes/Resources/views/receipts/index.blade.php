@extends('mes::layouts.app')

@section('title', '完工入库')
@section('sub', '报工完成后按批次入库，入满自动转完工')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi ok">
            <div class="lbl">今日入库</div>
            <div class="num">{{ number_format($stat['today_qty'], 0) }}<small>件</small></div>
            <div class="hint">{{ now()->toDateString() }}</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">本月入库</div>
            <div class="num">{{ number_format($stat['month_qty'], 0) }}<small>件</small></div>
            <div class="hint">{{ now()->format('Y-m') }}</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">累计批次</div>
            <div class="num">{{ $stat['lots'] }}<small>批</small></div>
            <div class="hint">可进「批次追溯」查上下游</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">待入库工单</div>
            <div class="num">{{ $openOrders->count() }}<small>张</small></div>
            <div class="hint">已下达 / 生产中 / 已完工</div>
        </div>
    </div>

    <div class="mes-card">
        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px">
            <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:260px" placeholder="批次号 / 入库单号 / 工单号 / 物料">
            <button class="btn btn-sm btn-primary">查询</button>
            <a class="btn btn-sm btn-light" href="{{ route('mes.receipts.index') }}">清空</a>
            <span class="mini" style="margin-left:auto">
                入库从工单详情发起，也可以直接用下面的「快速入库」。
            </span>
        </form>

        <table class="mes-table">
            <thead>
            <tr>
                <th>入库单号</th>
                <th>工单</th>
                <th>物料</th>
                <th>批次号</th>
                <th class="num">数量</th>
                <th>仓库</th>
                <th>入库时间</th>
                <th>操作人</th>
                <th>备注</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($receipts as $r)
                <tr>
                    <td><span class="mes-badge b-released">{{ $r->receipt_no }}</span></td>
                    <td>
                        @if ($r->workOrder)
                            <a href="{{ route('mes.work-orders.show', $r->workOrder) }}">{{ $r->workOrder->order_no }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $r->material?->code }} {{ $r->material?->name }}</td>
                    <td>
                        <a href="{{ route('mes.trace.show', $r->lot_no) }}">{{ $r->lot_no }}</a>
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $r->qty, 3), '0'), '.') }} {{ $r->material?->unit }}</td>
                    <td>{{ $r->warehouse }}</td>
                    <td>{{ $r->received_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $r->receiver?->name ?? '—' }}</td>
                    <td class="mini">{{ $r->remark ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="mes-empty">还没有入库记录。工单报工后，在工单详情页点「完工入库」即可。</div></td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:12px">{{ $receipts->links() }}</div>
    </div>

    <div class="mes-card">
        <div style="font-weight:600; margin-bottom:10px">快速入库（选一张工单）</div>
        <table class="mes-table">
            <thead>
            <tr><th>工单</th><th>产品</th><th class="num">计划</th><th class="num">已入库</th><th class="num">可入库</th><th>状态</th><th style="width:320px">入库</th></tr>
            </thead>
            <tbody>
            @forelse ($openOrders as $o)
                @php($received = (float) \App\Modules\Mes\Models\FinishedReceipt::query()->where('work_order_id', $o->id)->sum('qty'))
                @php($remain = max(0, round((float) $o->plan_qty - $received, 3)))
                <tr>
                    <td><a href="{{ route('mes.work-orders.show', $o) }}">{{ $o->order_no }}</a></td>
                    <td>{{ $o->product?->code }} {{ $o->product?->name }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $o->plan_qty, 3), '0'), '.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($received, 3), '0'), '.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($remain, 3), '0'), '.') }}</td>
                    <td><span class="mes-badge b-{{ $o->status }}">{{ $o->status }}</span></td>
                    <td>
                        @if ($remain > 0)
                            <form method="post" action="{{ route('mes.work-orders.receipts.store', $o) }}" style="display:flex; gap:6px">
                                @csrf
                                <input type="number" step="0.001" min="0.001" max="{{ $remain }}" name="qty" value="{{ $remain }}" class="form-control form-control-sm" style="width:96px">
                                <input type="text" name="lot_no" class="form-control form-control-sm" style="width:120px" placeholder="批次（自动）">
                                <button class="btn btn-sm btn-primary">入库</button>
                            </form>
                        @else
                            <span class="mini">已入满，去工单详情关单</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="mes-empty">没有可入库的工单。</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
