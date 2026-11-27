@extends('mes::layouts.app')

@section('title', $eq->code . ' · 设备档案')
@section('sub', '点检、报修、修复记录都在这一页')

@section('content')
    @php
        $due = $eq->last_check_at ? $eq->last_check_at->copy()->addDays((int) $eq->check_cycle_days) : null;
        $openRepairs = $eq->repairs->whereIn('status', ['open', 'handling'])->count();
    @endphp

    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px">
        <div>
            <div style="font-size:22px; font-weight:700; color:#16233f">{{ $eq->name }}</div>
            <div style="font-size:13px; color:#6b7a90; margin-top:4px">
                <span style="font-family:ui-monospace,Consolas,monospace">{{ $eq->code }}</span>
                · {{ $eq->workCenter?->name }}
                · 点检周期 {{ $eq->check_cycle_days }} 天
                · 上次点检 {{ $eq->last_check_at?->format('Y-m-d H:i') ?? '从未' }}
                @if ($due)
                    · 下次 {{ $due->format('m-d') }}{{ $due->isPast() ? '（已到期）' : '' }}
                @endif
            </div>
        </div>
        <div style="display:flex; gap:8px; align-items:center">
            <span class="mes-badge {{ match ($eq->status) {
                'running' => 'b-done', 'idle' => 'b-released', 'repairing' => 'b-bad', 'scrapped' => 'b-draft', default => 'b-draft',
            } }}" style="font-size:14px; padding:6px 12px">
                {{ ['running' => '运行中', 'idle' => '空闲', 'repairing' => '维修中', 'scrapped' => '已报废'][$eq->status] ?? $eq->status }}
            </span>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal">改资料</button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.equipment.index') }}">返回台账</a>
        </div>
    </div>

    @if ($openRepairs > 0)
        <div class="mes-card" style="margin-bottom:14px; border-left:4px solid #c92a2a; padding:12px 16px">
            <span class="mes-badge b-bad">停机</span>
            <span style="font-size:13px; color:#4a5568; margin-left:8px">
                这台设备有 {{ $openRepairs }} 张未闭环报修，修好后在下方报修记录里点「已修复」。
            </span>
        </div>
    @endif

    <div class="wo-grid">
        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">每日点检</div>

            <form method="post" action="{{ route('mes.equipment.checks.store', $eq) }}" style="margin-bottom:14px">
                @csrf
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:12px">点检结果</label>
                        <select name="result" class="form-select form-select-sm">
                            <option value="ok">正常</option>
                            <option value="ng">发现异常</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" style="font-size:12px">备注（异常项：气压 / 油位 / 温度 / 异响）</label>
                        <input name="remark" class="form-control form-control-sm" placeholder="如：油位偏低，已补充">
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px">
                    <div style="font-size:12px; color:#8a97a8">点检会把「上次点检」更新为当前时间</div>
                    <button class="btn btn-sm btn-primary">提交点检</button>
                </div>
            </form>

            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>点检时间</th><th>结果</th><th>点检人</th><th>备注</th></tr></thead>
                <tbody>
                @forelse ($eq->checks as $chk)
                    <tr>
                        <td style="font-family:ui-monospace,Consolas,monospace">{{ $chk->checked_at?->format('m-d H:i') }}</td>
                        <td>
                            <span class="mes-badge {{ $chk->result === 'ok' ? 'b-done' : 'b-bad' }}">{{ $chk->result === 'ok' ? '正常' : '异常' }}</span>
                        </td>
                        <td style="color:#6b7a90">{{ $chk->checker?->name ?? '—' }}</td>
                        <td style="color:#6b7a90; font-size:12px">{{ $chk->remark ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="mes-empty">还没点检记录。</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:10px">故障报修</div>

            <form method="post" action="{{ route('mes.equipment.repairs.store', $eq) }}" style="margin-bottom:14px">
                @csrf
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label" style="font-size:12px">故障描述</label>
                        <input name="fault_desc" class="form-control form-control-sm" placeholder="如：主轴异响、贴片偏位" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:12px">指派维修</label>
                        <select name="handler_id" class="form-select form-select-sm">
                            <option value="">待分配</option>
                            @foreach ($handlers as $h)
                                <option value="{{ $h->id }}">{{ $h->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px">
                    <div style="font-size:12px; color:#8a97a8">报修后设备自动置为「维修中」</div>
                    <button class="btn btn-sm btn-danger">报修</button>
                </div>
            </form>

            <table class="mes-table" style="font-size:13px">
                <thead><tr><th>故障</th><th>状态</th><th class="num">停机</th><th>处理</th></tr></thead>
                <tbody>
                @forelse ($eq->repairs as $rp)
                    <tr>
                        <td>
                            {{ $rp->fault_desc }}
                            <div style="font-size:11px; color:#8a97a8">{{ $rp->reporter?->name }} 报 · {{ $rp->created_at?->format('m-d H:i') }}</div>
                        </td>
                        <td>
                            <span class="mes-badge {{ $rp->status === 'open' ? 'b-draft' : ($rp->status === 'handling' ? 'b-progress' : 'b-done') }}">
                                {{ ['open' => '未受理', 'handling' => '处理中', 'fixed' => '已修复', 'closed' => '已关单'][$rp->status] ?? $rp->status }}
                            </span>
                            @if ($rp->handler)<div style="font-size:11px; color:#8a97a8">{{ $rp->handler->name }}</div>@endif
                        </td>
                        <td class="num">{{ $rp->down_minutes }} 分</td>
                       <td>
                            @if (in_array($rp->status, ['open', 'handling'], true))
                                <form method="post" action="{{ route('mes.equipment.repairs.update', $rp) }}" style="display:flex; gap:4px; align-items:center">
                                    @csrf
                                    @method('PUT')
                                    <select name="status" class="form-select form-select-sm" style="width:96px">
                                        <option value="handling" @selected($rp->status === 'handling')>处理中</option>
                                        <option value="fixed">已修复</option>
                                        <option value="closed">关单</option>
                                    </select>
                                    <input name="down_minutes" type="number" min="0" class="form-control form-control-sm" style="width:76px" placeholder="停机分" value="{{ $rp->down_minutes ?: '' }}">
                                    <button class="btn btn-sm btn-outline-primary">更新</button>
                                </form>
                            @else
                                <span style="color:#a8b3c1; font-size:12px">{{ $rp->fixed_at?->format('m-d H:i') }} 修复</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="mes-empty">没有报修记录，设备状态良好。</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('mes.equipment.update', $eq) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <div class="modal-title" style="font-weight:600">改设备资料</div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label" style="font-size:12px">名称</label>
                            <input name="name" class="form-control form-control-sm" value="{{ $eq->name }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:12px">工作中心</label>
                            <select name="work_center_id" class="form-select form-select-sm" required>
                                @foreach ($centers as $c)
                                    <option value="{{ $c->id }}" @selected($eq->work_center_id === $c->id)>{{ $c->code }} {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:12px">状态</label>
                            <select name="status" class="form-select form-select-sm">
                                @foreach (['running' => '运行中', 'idle' => '空闲', 'repairing' => '维修中', 'scrapped' => '已报废'] as $k => $v)
                                    <option value="{{ $k }}" @selected($eq->status === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:12px">点检周期（天）</label>
                            <input name="check_cycle_days" type="number" min="1" max="365" class="form-control form-control-sm" value="{{ $eq->check_cycle_days }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-size:12px">备注</label>
                            <input name="remark" class="form-control form-control-sm" value="{{ $eq->remark }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                    <button class="btn btn-sm btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
@endsection
