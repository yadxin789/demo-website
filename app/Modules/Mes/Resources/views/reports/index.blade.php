@extends('mes::layouts.app')

@section('title', '报工记录')
@section('sub', '每道工序每一次报工的流水账')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">今日报工</div>
            <div class="num">{{ $stat['today_count'] }}</div>
            <div class="hint">今天提交的笔数</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">今日合格</div>
            <div class="num">{{ rtrim(rtrim(number_format($stat['today_ok'], 3), '0'), '.') }}</div>
            <div class="hint">件</div>
        </div>
        <div class="mes-kpi {{ $stat['today_ng'] > 0 ? 'bad' : '' }}">
            <div class="lbl">今日不良</div>
            <div class="num">{{ rtrim(rtrim(number_format($stat['today_ng'], 3), '0'), '.') }}</div>
            <div class="hint">件</div>
        </div>
        <div class="mes-kpi orange">
            <div class="lbl">今日良率</div>
            <div class="num">{{ $stat['yield'] }}%</div>
            <div class="hint">合格 /（合格 + 不良）</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px">
            <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
                <input name="from" type="date" value="{{ $from }}" class="form-control form-control-sm" style="width:150px">
                <span style="color:#8a97a8; font-size:12px">至</span>
                <input name="to" type="date" value="{{ $to }}" class="form-control form-control-sm" style="width:150px">
                <input name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:200px" placeholder="工单号 / 工序名">
                <button class="btn btn-sm btn-outline-secondary">查询</button>
                @if ($from !== '' || $to !== '' || $q !== '')
                    <a href="{{ route('mes.reports.index') }}" class="btn btn-sm btn-link">重置</a>
                @endif
            </form>
            <div style="font-size:12px; color:#8a97a8">
                累计 {{ $stat['all_count'] }} 笔 · {{ $stat['all_minutes'] }} 分钟工时
            </div>
        </div>

        <table class="mes-table" style="font-size:13px">
            <thead>
            <tr>
                <th>报工时间</th>
                <th>工单</th>
                <th>工序</th>
                <th>操作工</th>
                <th>班次</th>
                <th class="num">合格</th>
                <th class="num">不良</th>
                <th class="num">工时</th>
                <th>备注</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($reports as $r)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $r->reported_at?->format('m-d H:i') }}</td>
                    <td>
                        <a href="{{ route('mes.work-orders.show', $r->work_order_id) }}" style="color:#16233f">
                            {{ $r->workOrder?->order_no }}
                        </a>
                        <div style="color:#8a97a8; font-size:12px">{{ $r->workOrder?->product?->name }}</div>
                    </td>
                    <td>{{ $r->operation?->op_no }} · {{ $r->operation?->op_name }}</td>
                    <td>{{ $r->user?->name ?? '—' }}</td>
                    <td style="color:#6b7a90">{{ $r->shift?->name ?? '—' }}</td>
                    <td class="num" style="color:#227a39">{{ rtrim(rtrim(number_format((float) $r->ok_qty, 3), '0'), '.') }}</td>
                    <td class="num" style="color:{{ (float) $r->defect_qty > 0 ? '#c92a2a' : '#a8b3c1' }}">
                        {{ rtrim(rtrim(number_format((float) $r->defect_qty, 3), '0'), '.') }}
                    </td>
                    <td class="num">{{ $r->minutes }}</td>
                    <td style="color:#6b7a90; font-size:12px">{{ $r->remark ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center; color:#8a97a8; padding:30px">这个时间段没有报工记录。</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:12px">{{ $reports->links() }}</div>
    </div>

    <div class="mes-card" style="margin-top:14px; font-size:12px; color:#6b7a90; line-height:1.9">
        口径：工单完成数取<b>最慢那道工序</b>的完成量（瓶颈决定产出），不良数是各工序之和。
        所以同一张单里前道报 500、后道报 300，工单只认 300 —— 堆在半成品区的不算产出。
    </div>
@endsection
