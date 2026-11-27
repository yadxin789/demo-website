@extends('mes::layouts.app')

@section('title', '检验单')
@section('sub', 'IQC 来料 / IPQC 过程 / OQC 出货，逐项录入实测值')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi orange">
            <div class="lbl">待检</div>
            <div class="num">{{ $stat['pending'] }}</div>
            <div class="hint">还没判定</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">今日已检</div>
            <div class="num">{{ $stat['today'] }}</div>
            <div class="hint">张</div>
        </div>
        <div class="mes-kpi {{ $stat['fail'] > 0 ? 'bad' : '' }}">
            <div class="lbl">判定不合格</div>
            <div class="num">{{ $stat['fail'] }}</div>
            <div class="hint">累计</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">待处理不良</div>
            <div class="num">{{ $stat['defects'] }}</div>
            <div class="hint">未结案</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px">
            <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
                <input name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:190px" placeholder="检验单号 / 工单号">
                <select name="type" class="form-select form-select-sm" style="width:110px">
                    <option value="">全部类型</option>
                    @foreach (['IQC', 'IPQC', 'OQC'] as $ty)
                        <option value="{{ $ty }}" @selected($type === $ty)>{{ $ty }}</option>
                    @endforeach
                </select>
                <select name="result" class="form-select form-select-sm" style="width:120px">
                    <option value="">全部结果</option>
                    <option value="pending" @selected($result === 'pending')>待检</option>
                    <option value="pass" @selected($result === 'pass')>合格</option>
                    <option value="fail" @selected($result === 'fail')>不合格</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary">查询</button>
                @if ($q !== '' || $type !== '' || $result !== '')
                    <a href="{{ route('mes.quality.inspections.index') }}" class="btn btn-sm btn-link">重置</a>
                @endif
            </form>
            <div style="display:flex; gap:8px">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.quality.templates.index') }}">检验模板</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.quality.defects.index') }}">不良记录</a>
            </div>
        </div>

        <table class="mes-table" style="font-size:13px">
            <thead>
            <tr>
                <th>检验单号</th>
                <th>工单 / 产品</th>
                <th>工序</th>
                <th>模板</th>
                <th>类型</th>
                <th>判定</th>
                <th class="num">超差项</th>
                <th>检验员 / 时间</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($inspections as $ins)
                @php $ngCount = $ins->results->where('verdict', 'ng')->count(); @endphp
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">
                        <a href="{{ route('mes.quality.inspections.show', $ins) }}" style="color:#16233f">{{ $ins->inspection_no }}</a>
                    </td>
                    <td>
                        <a href="{{ route('mes.work-orders.show', $ins->work_order_id) }}" style="color:#16233f">{{ $ins->workOrder?->order_no }}</a>
                        <div style="color:#8a97a8; font-size:12px">{{ $ins->workOrder?->product?->name }}</div>
                    </td>
                    <td style="color:#6b7a90">{{ $ins->operation ? $ins->operation->op_no . ' ' . $ins->operation->op_name : '整单' }}</td>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $ins->template?->code }}</td>
                    <td><span class="mes-badge {{ $ins->type === 'IQC' ? 'b-draft' : ($ins->type === 'IPQC' ? 'b-progress' : 'b-released') }}">{{ $ins->type }}</span></td>
                    <td>
                        @if ($ins->result === 'pass')
                            <span class="mes-badge b-done">合格</span>
                        @elseif ($ins->result === 'fail')
                            <span class="mes-badge b-bad">不合格</span>
                        @else
                            <span class="mes-badge b-released">待检</span>
                        @endif
                    </td>
                    <td class="num" style="color:{{ $ngCount > 0 ? '#c92a2a' : '#a8b3c1' }}">{{ $ngCount }}</td>
                    <td style="color:#6b7a90; font-size:12px">
                        {{ $ins->inspector?->name ?? '—' }}
                        @if ($ins->inspected_at)<div>{{ $ins->inspected_at->format('m-d H:i') }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="mes-empty">还没有检验单。工单完工或工序检查点会生成检验单。</div></td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:14px">{{ $inspections->links() }}</div>
    </div>
@endsection
