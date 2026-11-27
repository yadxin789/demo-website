@extends('mes::layouts.app')

@section('title', '设备台账')
@section('sub', '运行状态、点检周期、故障报修一张表看清')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">设备总数</div>
            <div class="num">{{ $stat['total'] }}</div>
            <div class="hint">台</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">运行中</div>
            <div class="num">{{ $stat['running'] }}</div>
            <div class="hint">在产</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">空闲</div>
            <div class="num">{{ $stat['idle'] }}</div>
            <div class="hint">待用</div>
        </div>
        <div class="mes-kpi {{ $stat['repairing'] > 0 ? 'bad' : '' }}">
            <div class="lbl">维修中</div>
            <div class="num">{{ $stat['repairing'] }}</div>
            <div class="hint">停机</div>
        </div>
        <div class="mes-kpi {{ $stat['checked_today'] < $stat['running'] ? 'orange' : 'ok' }}">
            <div class="lbl">今日已点检</div>
            <div class="num">{{ $stat['checked_today'] }}</div>
            <div class="hint">台</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px">
            <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
                <input name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:180px" placeholder="设备编号 / 名称">
                <select name="work_center" class="form-select form-select-sm" style="width:170px">
                    <option value="">全部工作中心</option>
                    @foreach ($centers as $c)
                        <option value="{{ $c->id }}" @selected((string) $wc === (string) $c->id)>{{ $c->code }} {{ $c->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select form-select-sm" style="width:120px">
                    <option value="">全部状态</option>
                    @foreach (['running' => '运行中', 'idle' => '空闲', 'repairing' => '维修中', 'scrapped' => '已报废'] as $k => $v)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary">查询</button>
            </form>
            <div style="font-size:12px; color:#8a97a8">点检周期到期的设备在「下次点检」列标红</div>
        </div>

        <table class="mes-table" style="font-size:13px">
            <thead>
            <tr>
                <th>设备编号</th>
                <th>名称</th>
                <th>工作中心</th>
                <th>状态</th>
                <th>上次点检</th>
                <th>下次点检</th>
                <th class="num">未闭环报修</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($equipments as $eq)
                @php
                    $due = $eq->last_check_at ? $eq->last_check_at->copy()->addDays((int) $eq->check_cycle_days) : null;
                    $overdue = $due && $due->isPast();
                @endphp
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">
                        <a href="{{ route('mes.equipment.show', $eq) }}" style="color:#16233f">{{ $eq->code }}</a>
                    </td>
                    <td>{{ $eq->name }}</td>
                    <td style="color:#6b7a90">{{ $eq->workCenter?->name ?? '—' }}</td>
                    <td>
                        <span class="mes-badge {{ match ($eq->status) {
                            'running' => 'b-done',
                            'idle' => 'b-released',
                            'repairing' => 'b-bad',
                            'scrapped' => 'b-draft',
                            default => 'b-draft',
                        } }}">
                            {{ ['running' => '运行中', 'idle' => '空闲', 'repairing' => '维修中', 'scrapped' => '已报废'][$eq->status] ?? $eq->status }}
                        </span>
                    </td>
                    <td style="color:#6b7a90; font-size:12px">{{ $eq->last_check_at?->format('m-d H:i') ?? '从未点检' }}</td>
                    <td style="font-size:12px; color:{{ $overdue ? '#c92a2a' : '#6b7a90' }}">
                        {{ $due?->format('m-d') ?? '—' }}
                        @if ($overdue)<div>已到期</div>@endif
                    </td>
                    <td class="num" style="color:{{ $eq->open_repairs > 0 ? '#c92a2a' : '#a8b3c1' }}">{{ $eq->open_repairs }}</td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.equipment.show', $eq) }}">点检 / 报修</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="mes-empty">还没有设备台账。</div></td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:14px">{{ $equipments->links() }}</div>
    </div>
@endsection
