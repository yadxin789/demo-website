@extends('mes::layouts.app')

@section('title', '生产排产')
@section('sub', '工作中心 × 未来 5 天的粗排示意，一键重排后色块即时刷新')

@push('styles')
    <style>
        .gantt { width: 100%; border-collapse: collapse; }
        .gantt th, .gantt td { border: 1px solid #e3ebf5; }
        .gantt thead th { background: #f5f8fc; font-size: 12px; color: #4a5568; font-weight: 600; padding: 7px 8px; }
        .gantt thead th .d { display: block; font-size: 11px; color: #8a97a8; font-weight: 400; }
        .gantt .rowhead { width: 168px; padding: 8px 10px; font-size: 13px; background: #fbfcfe; vertical-align: middle; }
        .gantt .rowhead b { display: block; }
        .gantt .rowhead span { font-size: 11px; color: #8a97a8; font-family: ui-monospace, Consolas, monospace; }
        .gantt .lane { position: relative; height: 54px; background:
            repeating-linear-gradient(90deg, #f7fafd 0, #f7fafd 24.9%, #eef3f9 25%, #eef3f9 25.1%);
            padding: 0; }
        .blk {
            position: absolute; top: 8px; height: 38px; border-radius: 5px; padding: 3px 6px;
            font-size: 11px; line-height: 1.25; color: #fff; overflow: hidden; text-decoration: none;
            box-shadow: 0 1px 2px rgba(16, 40, 70, .18);
        }
        .blk:hover { filter: brightness(1.08); color: #fff; }
        .blk b { display: block; font-size: 11.5px; font-weight: 600; }
        .blk span { opacity: .86; }
        .blk.overflow { outline: 2px solid #ff5b5b; outline-offset: -2px; }
        .blk.s-done { background: #37a06a; }
        .blk.s-progress { background: #e8761f; }
        .blk.s-released { background: #1677b8; }
        .blk.s-draft { background: #8a97a8; }
        .mini { font-size: 12px; color: #8a97a8; }
        .hour-axis { display: flex; justify-content: space-between; font-size: 11px; color: #a8b3c1; padding: 2px 2px 0; }
    </style>
@endpush

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">待排工序</div>
            <div class="num">{{ $pendingOps }}</div>
            <div class="hint">道 · 已下达/生产中工单的未完成工序</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">本周作业块</div>
            <div class="num">{{ $blocks }}</div>
            <div class="hint">个 · 已排 {{ number_format($scheduledMinutes) }} 分钟</div>
        </div>
        <div class="mes-kpi {{ $overflowMinutes > 0 ? 'bad' : 'ok' }}">
            <div class="lbl">窗口外待排</div>
            <div class="num">{{ number_format($overflowMinutes / 60, 1) }}<small>h</small></div>
            <div class="hint">小时 · {{ number_format($overflowMinutes) }} 分钟排不进</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">工作中心</div>
            <div class="num">{{ $centers->count() }}</div>
            <div class="hint">个 · 参与排产</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px">
            <div>
                <div style="font-weight:600; font-size:14px">工作中心 × 未来 5 天（08:00 – 20:00）</div>
                <div class="mini" style="margin-top:3px">块宽 = 该工序占用当日班时的比例；点块进工单详情</div>
            </div>
            <form method="post" action="{{ route('mes.aps.schedule') }}" style="margin:0">
                @csrf
                <button class="btn btn-sm btn-primary">一键排产</button>
            </form>
        </div>

        @if (session('status'))
            <div class="alert alert-success py-2" style="font-size:13px">{{ session('status') }}</div>
        @endif

        @if ($blocks === 0)
            <div class="mes-empty" style="margin-bottom:12px">
                未来 5 天还没有排产结果。点右上角「一键排产」，系统会按各工作中心日产能把待排工序顺排进来。
            </div>
        @endif

        <table class="gantt">
            <thead>
            <tr>
                <th style="width:168px">工作中心</th>
                @for ($i = 0; $i < $days; $i++)
                    @php $d = $from->copy()->addDays($i); @endphp
                    <th>{{ $d->format('m-d') }} <span class="d">{{ ['周日','周一','周二','周三','周四','周五','周六'][$d->dayOfWeek] }}{{ $i === 0 ? ' · 今天' : '' }}</span></th>
                @endfor
            </tr>
            </thead>
            <tbody>
            @foreach ($centers as $c)
                <tr>
                    <td class="rowhead">
                        <b>{{ $c->name }}</b>
                        <span>{{ $c->code }}</span>
                        <div class="mini" style="margin-top:4px">负荷 {{ $load[$c->id]['pct'] }}% · {{ $load[$c->id]['blocks'] }} 块</div>
                    </td>
                    @for ($i = 0; $i < $days; $i++)
                        <td class="lane">
                            @foreach ($grid[$c->id][$i] as $b)
                                <a class="blk s-{{ $b['order_status'] === 'in_progress' ? 'progress' : ($b['order_status'] === 'completed' ? 'done' : ($b['order_status'] === 'draft' ? 'draft' : 'released')) }} {{ $b['overflow'] ? 'overflow' : '' }}"
                                   style="left: {{ $b['left'] }}%; width: {{ $b['width'] }}%"
                                   href="{{ route('mes.work-orders.show', $b['work_order_id']) }}"
                                   title="{{ $b['order_no'] }} · 工序 {{ $b['op_no'] }} {{ $b['op_name'] }} · {{ $b['start'] }}–{{ $b['end'] }} · 计划 {{ number_format($b['qty'], 0) }} / 完成 {{ number_format($b['done'], 0) }}">
                                    <b>{{ $b['order_no'] }}</b>
                                    <span>{{ $b['op_name'] }} · {{ number_format($b['qty'], 0) }} 件</span>
                                </a>
                            @endforeach
                        </td>
                    @endfor
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="hour-axis" style="max-width:calc(100% - 168px); margin-left:168px">
            <span>08:00</span><span>11:00</span><span>14:00</span><span>17:00</span><span>20:00</span>
        </div>

        <div class="mini" style="margin-top:12px; line-height:1.7">
            窗口产能：{{ $centers->count() }} 个中心 × {{ $days }} 天 × 12 小时 = {{ number_format($capacityMinutes) }} 分钟，
            已排 {{ number_format($scheduledMinutes) }} 分钟（{{ $capacityMinutes > 0 ? round($scheduledMinutes / $capacityMinutes * 100, 1) : 0 }}%），
            待排总量 {{ number_format($pendingMinutes) }} 分钟。
            <br>
            口径说明：这是<b>粗排示意</b>，不是可执行排程——按「交期 → 优先级 → 工序号」顺序贪心填满各工作中心，
            单工序工时 = (计划数 − 已完成数) × 工序标准分钟，填满一天就顺延次日、最多排到第 {{ $days }} 天；
            放不下的计入「需加班 / 外协 / 顺延」。真实 APS 还需要设备日历、换型时间、工装模具约束，演示版刻意不做（规划书 §0.3）。
        </div>
    </div>
@endsection
