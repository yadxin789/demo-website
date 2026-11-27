@extends('mes::layouts.app')

@section('title', '保养与维修单')
@section('sub', '计划保养（PM）与故障维修（CM）统一排期、统一结算工时')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi orange">
            <div class="lbl">待执行</div>
            <div class="num">{{ $stat['pending'] }}<small>张</small></div>
            <div class="hint">还没人接手</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">执行中</div>
            <div class="num">{{ $stat['doing'] }}<small>张</small></div>
            <div class="hint">已派工、正在做</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">已完成</div>
            <div class="num">{{ $stat['done'] }}<small>张</small></div>
            <div class="hint">历史累计</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">维修工时</div>
            <div class="num">{{ number_format($stat['hours'], 1) }}<small>h</small></div>
            <div class="hint">已完成单合计</div>
        </div>
    </div>

    <div class="mes-card">
        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px">
            <select name="type" class="form-select form-select-sm" style="width:140px">
                <option value="">全部类型</option>
                <option value="pm" @selected($type === 'pm')>计划保养</option>
                <option value="cm" @selected($type === 'cm')>故障维修</option>
            </select>
            <select name="status" class="form-select form-select-sm" style="width:140px">
                <option value="">全部状态</option>
                <option value="pending" @selected($status === 'pending')>待执行</option>
                <option value="in_progress" @selected($status === 'in_progress')>执行中</option>
                <option value="done" @selected($status === 'done')>已完成</option>
            </select>
            <button class="btn btn-sm btn-primary">筛选</button>
            <a class="btn btn-sm btn-light" href="{{ route('mes.maintenance.plans') }}">回保养计划</a>
        </form>

        <table class="mes-table">
            <thead>
            <tr><th>单号</th><th>设备</th><th>来源计划</th><th>类型</th><th>到期</th><th>状态</th><th>完成时间</th><th class="num">工时</th><th style="width:260px">完成</th></tr>
            </thead>
            <tbody>
            @forelse ($orders as $o)
                <tr>
                    <td><span class="mes-badge b-released">{{ $o->order_no }}</span></td>
                    <td>{{ $o->equipment?->code }} {{ $o->equipment?->name }}</td>
                    <td>{{ $o->plan?->code ?? '—' }}</td>
                    <td>{{ $o->type === 'pm' ? '计划保养' : '故障维修' }}</td>
                    <td>{{ $o->due_at?->toDateString() ?? '—' }}</td>
                    <td><span class="mes-badge b-{{ $o->status === 'done' ? 'completed' : ($o->status === 'pending' ? 'draft' : 'in_progress') }}">{{ $o->status }}</span></td>
                    <td>{{ $o->done_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $o->cost_hours, 2) }}</td>
                    <td>
                        @if ($o->status !== 'done')
                            <form method="post" action="{{ route('mes.maintenance.orders.complete', $o) }}" style="display:flex; gap:6px">
                                @csrf
                                <input type="number" step="0.25" min="0" name="cost_hours" value="{{ round((float) ($o->plan?->standard_minutes ?? 60) / 60, 2) }}" class="form-control form-control-sm" style="width:88px">
                                <input type="text" name="remark" class="form-control form-control-sm" style="width:130px" placeholder="保养记录">
                                <button class="btn btn-sm btn-primary">完成</button>
                            </form>
                        @else
                            <span class="mini">{{ $o->assignee?->name ?? '已归档' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="mes-empty">没有符合条件的保养/维修单。</div></td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:12px">{{ $orders->links() }}</div>
    </div>
@endsection
