@extends('mes::layouts.app')

@section('title', '保养计划')
@section('sub', '预防性保养：按周期到期自动生成保养工单')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">保养计划</div>
            <div class="num">{{ $stat['total'] }}<small>条</small></div>
            <div class="hint">覆盖全部在册设备</div>
        </div>
        <div class="mes-kpi {{ $stat['due'] > 0 ? 'bad' : 'ok' }}">
            <div class="lbl">已到期</div>
            <div class="num">{{ $stat['due'] }}<small>条</small></div>
            <div class="hint">{{ $stat['due'] > 0 ? '点右上"生成保养单"排掉' : '全部在周期内' }}</div>
        </div>
        <div class="mes-kpi orange">
            <div class="lbl">未完成保养单</div>
            <div class="num">{{ $stat['open_orders'] }}<small>张</small></div>
            <div class="hint">待执行 / 执行中</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">本月完成</div>
            <div class="num">{{ $stat['done_month'] }}<small>张</small></div>
            <div class="hint">含计划保养与故障维修</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px">
            <div style="font-weight:600">保养计划</div>
            <form method="post" action="{{ route('mes.maintenance.generate') }}" style="margin:0 0 0 auto">
                @csrf
                <button class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-wrench"></i> 把到期计划生成保养单
                </button>
            </form>
            <a class="btn btn-sm btn-light" href="{{ route('mes.maintenance.orders') }}">看保养/维修单</a>
        </div>

        <table class="mes-table">
            <thead>
            <tr>
                <th>编号</th><th>计划名称</th><th>设备</th><th class="num">周期</th>
                <th>上次保养</th><th>下次到期</th><th>状态</th><th style="width:90px">操作</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($plans as $p)
                <tr>
                    <td><span class="mes-badge b-released">{{ $p->code }}</span></td>
                    <td>{{ $p->name }}</td>
                    <td>{{ $p->equipment?->code }} {{ $p->equipment?->name }}</td>
                    <td class="num">{{ $p->cycle_days }} 天</td>
                    <td>{{ $p->last_done_at?->toDateString() ?? '—' }}</td>
                    <td>
                        @if ($p->next_due_at)
                            <span style="color:{{ $p->is_due ? '#c92a2a' : '#4a5568' }}">
                                {{ $p->next_due_at->toDateString() }}
                                @if ($p->is_due)（逾期 {{ $p->overdue_days }} 天）@endif
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if (! $p->is_active)
                            <span class="mes-badge b-closed">停用</span>
                        @elseif ($p->is_due)
                            <span class="mes-badge b-in_progress">到期</span>
                        @else
                            <span class="mes-badge b-released">周期内</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#plan-{{ $p->id }}">编辑</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="mes-empty">还没有保养计划，用下面的表单建第一条。</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="wo-grid">
        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">新建保养计划</div>
            <form method="post" action="{{ route('mes.maintenance.plans.store') }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label">计划编号</label>
                    <input name="code" value="{{ old('code', $nextCode) }}" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">计划名称</label>
                    <input name="name" value="{{ old('name') }}" class="form-control form-control-sm" placeholder="如：注塑机月度保养" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">设备</label>
                    <select name="equipment_id" class="form-select form-select-sm" required>
                        <option value="">请选择</option>
                        @foreach ($equipments as $e)
                            <option value="{{ $e->id }}">{{ $e->code }} {{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2" style="display:flex; gap:8px">
                    <div style="flex:1">
                        <label class="form-label">周期（天）</label>
                        <input type="number" name="cycle_days" value="{{ old('cycle_days', 30) }}" class="form-control form-control-sm" min="1" required>
                    </div>
                    <div style="flex:1">
                        <label class="form-label">标准工时（分）</label>
                        <input type="number" name="standard_minutes" value="{{ old('standard_minutes', 60) }}" class="form-control form-control-sm" min="1">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">上次保养（可空）</label>
                    <input type="date" name="last_done_at" value="{{ old('last_done_at') }}" class="form-control form-control-sm">
                </div>
                <div class="mb-3">
                    <label class="form-label">备注</label>
                    <input name="remark" value="{{ old('remark') }}" class="form-control form-control-sm" placeholder="保养要点，如：换油、清滤芯">
                </div>
                <button class="btn btn-primary btn-sm">保存计划</button>
            </form>
        </div>

        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">待执行的保养 / 维修单</div>
            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>单号</th><th>设备</th><th>类型</th><th>到期</th><th>状态</th></tr></thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr>
                        <td><a href="{{ route('mes.maintenance.orders') }}">{{ $o->order_no }}</a></td>
                        <td>{{ $o->equipment?->code }}</td>
                        <td>{{ $o->type === 'pm' ? '保养' : '维修' }}</td>
                        <td>{{ $o->due_at?->toDateString() ?? '—' }}</td>
                        <td><span class="mes-badge b-{{ $o->status }}">{{ $o->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="mes-empty">没有待执行的单子。</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($plans as $p)
        <div class="modal fade" id="plan-{{ $p->id }}" tabindex="-1">
            <div class="modal-dialog"><div class="modal-content">
                <form method="post" action="{{ route('mes.maintenance.plans.update', $p) }}">
                    @csrf @method('PUT')
                    <div class="modal-header"><div class="modal-title">编辑 {{ $p->code }}</div></div>
                    <div class="modal-body">
                        <div class="mb-2"><label class="form-label">计划编号</label><input name="code" value="{{ $p->code }}" class="form-control form-control-sm" required></div>
                        <div class="mb-2"><label class="form-label">计划名称</label><input name="name" value="{{ $p->name }}" class="form-control form-control-sm" required></div>
                        <div class="mb-2"><label class="form-label">设备</label>
                            <select name="equipment_id" class="form-select form-select-sm">
                                @foreach ($equipments as $e)
                                    <option value="{{ $e->id }}" @selected($e->id === $p->equipment_id)>{{ $e->code }} {{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2" style="display:flex; gap:8px">
                            <div style="flex:1"><label class="form-label">周期（天）</label><input type="number" name="cycle_days" value="{{ $p->cycle_days }}" class="form-control form-control-sm" min="1"></div>
                            <div style="flex:1"><label class="form-label">标准工时（分）</label><input type="number" name="standard_minutes" value="{{ $p->standard_minutes }}" class="form-control form-control-sm" min="1"></div>
                        </div>
                        <div class="mb-2"><label class="form-label">上次保养</label><input type="date" name="last_done_at" value="{{ $p->last_done_at?->toDateString() }}" class="form-control form-control-sm"></div>
                        <div class="mb-2"><label class="form-label">备注</label><input name="remark" value="{{ $p->remark }}" class="form-control form-control-sm"></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-sm btn-primary">保存</button>
                    </div>
                </form>
            </div></div>
        </div>
    @endforeach
@endsection
