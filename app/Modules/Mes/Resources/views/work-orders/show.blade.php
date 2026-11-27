@extends('mes::layouts.app')

@section('title', $order->order_no)
@section('sub', ($order->product?->name ?? '') . ' · 计划 ' . (float) $order->plan_qty . ' ' . ($order->product?->unit ?? '') . ' · ' . ($order->customer ?: '无客户标记'))

@section('content')
    @php
        $map = [
            'draft' => ['b-draft', '草稿'],
            'released' => ['b-released', '已下达'],
            'in_progress' => ['b-progress', '生产中'],
            'completed' => ['b-done', '已完工'],
            'closed' => ['b-draft', '已关闭'],
        ];
        $opMap = [
            'pending' => ['b-draft', '待派工'],
            'dispatched' => ['b-released', '已派工'],
            'in_progress' => ['b-progress', '进行中'],
            'done' => ['b-done', '已完成'],
        ];
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    @endphp

    <div class="mes-card" style="margin-bottom:14px">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:18px; flex-wrap:wrap">
            <div style="min-width:260px">
                <div style="font-size:12px; color:#6b7a90; margin-bottom:6px">
                    <a href="{{ route('mes.work-orders.index') }}" style="color:#0d6efd">← 返回工单列表</a>
                </div>
                <div style="display:flex; align-items:center; gap:10px">
                    <div style="font-size:22px; font-weight:700; font-family:ui-monospace,Consolas,monospace">{{ $order->order_no }}</div>
                    <span class="mes-badge {{ $map[$order->status][0] ?? 'b-draft' }}">{{ $map[$order->status][1] ?? $order->status }}</span>
                    @if ($order->priority <= 3)
                        <span class="mes-badge b-bad">优先 {{ $order->priority }}</span>
                    @endif
                </div>
                <div style="color:#6b7a90; font-size:13px; margin-top:6px">
                    {{ $order->product?->code }} · {{ $order->product?->spec ?: '标准规格' }}
                    @if ($order->routing)
                        · 工艺 <a href="{{ route('mes.master.routings.show', $order->routing) }}" style="color:#0d6efd">{{ $order->routing->code }}</a>
                    @endif
                    @if ($order->bom)
                        · BOM <a href="{{ route('mes.master.boms.show', $order->bom) }}" style="color:#0d6efd">{{ $order->bom->code }}</a>
                    @endif
                </div>
                <div style="display:flex; gap:22px; margin-top:12px; font-size:12px; color:#6b7a90">
                    <div>计划开工 <b style="color:#16233f">{{ $order->plan_start?->format('Y-m-d') ?? '—' }}</b></div>
                    <div>计划完工 <b style="color:#16233f">{{ $order->plan_end?->format('Y-m-d') ?? '—' }}</b></div>
                    <div>优先级 <b style="color:#16233f">{{ $order->priority }}</b></div>
                </div>
            </div>

            <div style="flex:1; min-width:280px; max-width:460px">
                <div style="display:flex; justify-content:space-between; font-size:12px; color:#6b7a90">
                    <span>完成进度</span>
                    <span><b style="color:#16233f">{{ $qty($order->done_qty) }}</b> / {{ $qty($order->plan_qty) }} （{{ $order->progress }}%）</span>
                </div>
                <div class="mes-progress" style="height:10px; margin-top:6px"><span style="width:{{ min(100, $order->progress) }}%"></span></div>
                <div style="display:flex; gap:18px; margin-top:10px; font-size:12px; color:#6b7a90">
                    <span>不良 <b style="color:#c92a2a">{{ $qty($order->defect_qty) }}</b></span>
                    <span>已报工 <b style="color:#16233f">{{ $qty($reportedOk) }}</b></span>
                    <span>工时 <b style="color:#16233f">{{ $reportedMinutes }}</b> 分钟</span>
                </div>
            </div>
        </div>

        <div style="margin-top:14px; padding-top:12px; border-top:1px dashed #e2e8f0; display:flex; gap:8px; flex-wrap:wrap">
            @if ($order->status === 'draft')
                <form method="post" action="{{ route('mes.work-orders.release', $order) }}">
                    @csrf
                    <button class="btn btn-sm btn-primary">下达工单（按工艺生成工序）</button>
                </form>
            @endif
            @if (in_array($order->status, ['released', 'in_progress'], true))
                <form method="post" action="{{ route('mes.work-orders.dispatch-all', $order) }}">
                    @csrf
                    <button class="btn btn-sm btn-primary">一键派工给{{ $leader?->name ?? '车间' }}</button>
                </form>
            @endif
            @if (! in_array($order->status, ['completed', 'closed'], true))
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal">修改数量 / 日期</button>
            @endif
            @if ($order->issues->isNotEmpty())
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.issues.show', $order->issues->first()) }}">
                    领料单 {{ $order->issues->first()->issue_no }}
                </a>
            @elseif ($order->bom && ! in_array($order->status, ['draft', 'closed'], true))
                <form method="post" action="{{ route('mes.work-orders.issues.store', $order) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">生成领料单</button>
                </form>
            @endif
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.kanban') }}">去车间看板</a>
        </div>
    </div>

    @if (count($shortages) > 0)
        <div class="mes-card" style="margin-bottom:14px; border-left:4px solid #d6336c">
            <div style="font-weight:600; margin-bottom:8px; color:#a61e4d">
                齐套检查：{{ count($shortages) }} 项原料不够这张单用
            </div>
            <div style="font-size:13px; color:#4a5568; line-height:1.9">
                @foreach ($shortages as $s)
                    <div>
                        <span style="font-family:ui-monospace,Consolas,monospace">{{ $s['code'] }}</span>
                        {{ $s['name'] }} —— 需要 {{ $s['need'] }} {{ $s['unit'] }}，库存 {{ $s['stock'] }}，差 <b style="color:#a61e4d">{{ $s['gap'] }}</b>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($order->bom)
        <div class="mes-card" style="margin-bottom:14px; border-left:4px solid #2f9e44; padding:12px 16px">
            <span class="mes-badge b-done">齐套</span>
            <span style="font-size:13px; color:#4a5568; margin-left:8px">按 BOM 核算，这张单要的料现有库存都够。</span>
        </div>
    @endif

    @php $issue = $order->issues->first(); @endphp
    @if ($issue && $issue->status !== 'issued')
        @php
            $ireq = (float) $issue->lines->sum('required_qty');
            $igot = (float) $issue->lines->sum('issued_qty');
            $irate = $ireq > 0 ? (int) round($igot / $ireq * 100) : 0;
        @endphp
        <div class="mes-card" style="margin-bottom:14px; border-left:4px solid #f08c00; padding:12px 16px">
            <span class="mes-badge b-progress">领料未发齐</span>
            <span style="font-size:13px; color:#4a5568; margin-left:8px">
                领料单
                <a href="{{ route('mes.issues.show', $issue) }}" style="color:#16233f; font-weight:600">{{ $issue->issue_no }}</a>
                只发了 {{ $irate }}%，产线这道单在等料，发齐才能连续生产。
            </span>
        </div>
    @endif

    <div class="wo-grid">
        <div class="mes-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
                <div style="font-weight:600">工序进度</div>
                <div style="font-size:12px; color:#8a97a8">共 {{ $order->operations->count() }} 道</div>
            </div>

            @if ($order->operations->isEmpty())
                <div class="mes-empty">
                    @if ($order->status === 'draft')
                        工单还是草稿。点上面的「下达工单」，系统会按工艺路线把工序生成出来。
                    @else
                        这张单没有工序 —— 产品可能还没有工艺路线，去主数据补一条再下达。
                    @endif
                </div>
            @else
                <div class="wo-tl">
                    @foreach ($order->operations as $op)
                        <div class="wo-tl-item">
                            <span class="wo-tl-dot {{ $op->status === 'done' ? 'done' : (in_array($op->status, ['dispatched', 'in_progress'], true) ? 'on' : '') }}"></span>
                            <div class="wo-tl-head">
                                <span class="wo-tl-op">{{ $op->op_no }}</span>
                                <span class="wo-tl-name">{{ $op->op_name }}</span>
                                <span class="mes-badge {{ $opMap[$op->status][0] ?? 'b-draft' }}">{{ $opMap[$op->status][1] ?? $op->status }}</span>
                                <span class="wo-tl-act">
                                    <button class="btn btn-sm btn-primary py-0 px-2"
                                            data-bs-toggle="modal" data-bs-target="#reportModal"
                                            onclick='fillReport({{ $op->id }}, {{ $op->op_no }}, @json($op->op_name), {{ (float) $op->plan_qty }}, {{ (float) $op->done_qty }})'>
                                        报工
                                    </button>
                                    @if ($op->status !== 'done')
                                        <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                data-bs-toggle="modal" data-bs-target="#dispatchModal"
                                                onclick='fillDispatch({{ $op->id }}, {{ $op->op_no }}, @json($op->op_name), {{ $op->assignee_id ?: "null" }}, @json($op->team))'>
                                            {{ ($op->assignee_id || $op->team) ? '改派' : '派工' }}
                                        </button>
                                    @endif
                                </span>
                            </div>
                            <div class="wo-tl-meta">
                                {{ $op->workCenter?->name ?? '未指定工作中心' }}
                                @if ($op->std_minutes)
                                    · 标准 {{ (float) $op->std_minutes }} 分钟
                                @endif
                                · 完成 {{ $qty($op->done_qty) }} / {{ $qty($op->plan_qty) }}
                                @if ($op->assignee)
                                    · 负责人 <b>{{ $op->assignee->name }}</b>
                                @endif
                                @if ($op->team)
                                    · {{ $op->team }}
                                @endif
                                @if ($op->started_at)
                                    · 开工 {{ $op->started_at->format('m-d H:i') }}
                                @endif
                                @if ($op->finished_at)
                                    · 完工 {{ $op->finished_at->format('m-d H:i') }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mes-card">
            <div style="font-weight:600; margin-bottom:12px">最近报工</div>
            @if ($order->reports->isEmpty())
                <div class="mes-empty">还没有报工记录。</div>
            @else
                <table class="mes-table" style="font-size:12px">
                    <thead>
                    <tr><th>时间</th><th>操作工</th><th class="num">合格</th><th class="num">不良</th><th class="num">工时</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($order->reports as $r)
                        <tr>
                            <td>{{ $r->reported_at?->format('m-d H:i') }}</td>
                            <td>{{ $r->user?->name ?? '—' }}<div style="color:#8a97a8; font-size:11px">{{ $r->shift?->name }}</div></td>
                            <td class="num" style="color:#227a39">{{ $qty($r->ok_qty) }}</td>
                            <td class="num" style="color:#c92a2a">{{ $qty($r->defect_qty) }}</td>
                            <td class="num">{{ $r->minutes }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            <div style="margin-top:16px; font-weight:600; margin-bottom:8px">检验</div>
            @if ($order->inspections->isEmpty())
                <div style="font-size:12px; color:#8a97a8">这张单还没有检验记录。</div>
            @else
                @foreach ($order->inspections as $ins)
                    <div style="font-size:12px; padding:6px 0; border-bottom:1px dashed #eef1f6">
                        <span class="mes-badge {{ $ins->result === 'pass' ? 'b-done' : ($ins->result === 'fail' ? 'b-bad' : 'b-draft') }}">
                            {{ ['pass' => '合格', 'fail' => '不合格', 'pending' => '待判定'][$ins->result] ?? $ins->result }}
                        </span>
                        <span style="margin-left:6px">{{ $ins->template?->name ?? '检验' }}</span>
                        <span style="color:#8a97a8; margin-left:6px">{{ $ins->inspected_at?->format('m-d H:i') }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="modal fade" id="dispatchModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <form class="modal-content" method="post" id="dispatchForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="dispatchTitle">派工</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">负责人</label>
                        <select name="assignee_id" class="form-select" id="dispatchAssignee">
                            <option value="">不指定</option>
                            @foreach ($assignees as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">班组</label>
                        <input name="team" class="form-control" id="dispatchTeam" placeholder="一号车间">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                    <button class="btn btn-primary" type="submit">确认派工</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('mes.work-orders.update', $order) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">修改工单</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="product_material_id" value="{{ $order->product_material_id }}">
                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label">计划数量</label>
                            <input name="plan_qty" type="number" step="0.001" class="form-control" value="{{ (float) $order->plan_qty }}" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">计划开工</label>
                            <input name="plan_start" type="date" class="form-control" value="{{ $order->plan_start?->format('Y-m-d') }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label">计划完工</label>
                            <input name="plan_end" type="date" class="form-control" value="{{ $order->plan_end?->format('Y-m-d') }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label">优先级</label>
                            <input name="priority" type="number" min="1" max="9" class="form-control" value="{{ $order->priority }}">
                        </div>
                        <div class="col-8">
                            <label class="form-label">客户</label>
                            <input name="customer" class="form-control" value="{{ $order->customer }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">备注</label>
                            <input name="remark" class="form-control" value="{{ $order->remark }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                    <button class="btn btn-primary" type="submit">保存</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" id="reportForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="reportTitle">报工</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="reportHint" style="font-size:12px; color:#6b7a90; margin-bottom:10px"></div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">合格数</label>
                            <input name="ok_qty" type="number" step="0.001" min="0" class="form-control" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">不良数</label>
                            <input name="defect_qty" type="number" step="0.001" min="0" class="form-control" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">工时（分钟）</label>
                            <input name="minutes" type="number" min="0" max="1440" class="form-control" value="60">
                        </div>
                        <div class="col-6">
                            <label class="form-label">班次</label>
                            <select name="shift_id" class="form-select" required>
                                <option value="">请选择班次</option>
                                @foreach ($shifts as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">备注</label>
                            <input name="remark" class="form-control" placeholder="可留空，例如：首件偏位已调整">
                        </div>
                    </div>
                    @if ($errors->any())
                        <div class="alert alert-danger py-2 mt-3 mb-0">{{ $errors->first() }}</div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                    <button class="btn btn-primary" type="submit">提交报工</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function fillReport(opId, opNo, opName, planQty, doneQty) {
    const form = document.getElementById('reportForm');
    form.action = "/mes/work-orders/{{ $order->id }}/operations/" + opId + "/report";
    document.getElementById('reportTitle').textContent = '报工 · ' + opNo + ' ' + opName;
    document.getElementById('reportHint').textContent =
        '这道工序计划 ' + planQty + ' 件，已完成 ' + doneQty + ' 件，还剩 ' + Math.max(0, planQty - doneQty) + ' 件。';
    form.querySelector('[name=ok_qty]').value = Math.max(0, planQty - doneQty) || 0;
    form.querySelector('[name=defect_qty]').value = 0;

    // 班次默认取第一个，减少操作步骤（提交仍要求选班次）
    const shift = form.querySelector('[name=shift_id]');
    if (shift && ! shift.value && shift.options.length > 1) {
        shift.selectedIndex = 1;
    }
}

function fillDispatch(opId, opNo, opName, assigneeId, team) {
    const form = document.getElementById('dispatchForm');
    form.action = "/mes/work-orders/{{ $order->id }}/operations/" + opId + "/dispatch";
    document.getElementById('dispatchTitle').textContent = '派工 · ' + opNo + ' ' + opName;
    document.getElementById('dispatchAssignee').value = assigneeId || '';
    document.getElementById('dispatchTeam').value = team || '一号车间';
}
</script>
@endpush

