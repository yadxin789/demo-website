@extends('mes::layouts.app')

@section('title', '生产工单')
@section('sub', '从下达、派工到报工的全过程')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi orange">
            <div class="lbl">在制工单</div>
            <div class="num">{{ $stat['open'] }}</div>
            <div class="hint">待投产 {{ $stat['planned'] }} 件</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">待下达</div>
            <div class="num">{{ $stat['draft'] }}</div>
            <div class="hint">草稿，还没进车间</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">已完工</div>
            <div class="num">{{ $stat['completed'] }}</div>
            <div class="hint">累计完成入库</div>
        </div>
        <div class="mes-kpi {{ $stat['overdue'] > 0 ? 'bad' : '' }}">
            <div class="lbl">延期风险</div>
            <div class="num">{{ $stat['overdue'] }}</div>
            <div class="hint">计划完工日已过、尚未完工</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px">
            <form method="get" style="display:flex; gap:8px; flex-wrap:wrap">
                <input name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:230px" placeholder="工单号 / 产品 / 客户">
                <select name="status" class="form-select form-select-sm" style="width:130px">
                    <option value="">全部状态</option>
                    <option value="open" @selected($status === 'open')>只看在制</option>
                    <option value="draft" @selected($status === 'draft')>草稿</option>
                    <option value="released" @selected($status === 'released')>已下达</option>
                    <option value="in_progress" @selected($status === 'in_progress')>生产中</option>
                    <option value="completed" @selected($status === 'completed')>已完工</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary">筛选</button>
                @if ($q !== '' || $status !== '')
                    <a href="{{ route('mes.work-orders.index') }}" class="btn btn-sm btn-link">重置</a>
                @endif
            </form>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#woModal">+ 新建工单</button>
        </div>

        <table class="mes-table">
            <thead>
            <tr>
                <th>工单号</th>
                <th>产品</th>
                <th>状态</th>
                <th>数量进度</th>
                <th>工序</th>
                <th>计划完工</th>
                <th>客户</th>
                <th style="width:96px"></th>
            </tr>
            </thead>
            <tbody>
            @php
                $map = [
                    'draft' => ['b-draft', '草稿'],
                    'released' => ['b-released', '已下达'],
                    'in_progress' => ['b-progress', '生产中'],
                    'completed' => ['b-done', '已完工'],
                    'closed' => ['b-draft', '已关闭'],
                ];
            @endphp
            @forelse ($orders as $o)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">
                        <a href="{{ route('mes.work-orders.show', $o) }}" style="color:#16233f">{{ $o->order_no }}</a>
                        @if ($o->priority <= 3)
                            <span class="mes-badge b-bad" style="margin-left:4px">急</span>
                        @endif
                    </td>
                    <td>
                        {{ $o->product?->name ?? '—' }}
                        <div style="color:#8a97a8; font-size:12px">{{ $o->product?->code }}</div>
                    </td>
                    <td>
                        <span class="mes-badge {{ $map[$o->status][0] ?? 'b-draft' }}">{{ $map[$o->status][1] ?? $o->status }}</span>
                    </td>
                    <td style="min-width:150px">
                        <div style="display:flex; justify-content:space-between; font-size:12px">
                            <span>{{ rtrim(rtrim(number_format((float) $o->done_qty, 3), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) $o->plan_qty, 3), '0'), '.') }}</span>
                            <span style="color:#8a97a8">{{ $o->progress }}%</span>
                        </div>
                        <div class="mes-progress" style="margin-top:4px"><span style="width:{{ min(100, $o->progress) }}%"></span></div>
                    </td>
                    <td class="num">{{ $o->ops_done }} / {{ $o->ops_total }}</td>
                    <td>
                        {{ $o->plan_end?->format('m-d') ?? '—' }}
                        @if ($o->plan_end && $o->status !== 'completed' && $o->plan_end->isPast())
                            <span style="color:#c92a2a; font-size:11px">已过</span>
                        @endif
                    </td>
                    <td style="color:#6b7a90">{{ $o->customer ?: '—' }}</td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary py-0 px-2" href="{{ route('mes.work-orders.show', $o) }}">详情</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; color:#8a97a8; padding:30px">没有符合条件的工单。</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:12px">{{ $orders->links() }}</div>
    </div>

    <div class="modal fade" id="woModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('mes.work-orders.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">新建生产工单</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
                    @endif
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">产品（成品 / 半成品）</label>
                            <select name="product_material_id" class="form-select" required>
                                <option value="">请选择</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}" @selected(old('product_material_id') == $p->id)>
                                        {{ $p->code }} · {{ $p->name }}（{{ $p->unit }}）
                                    </option>
                                @endforeach
                            </select>
                            <div style="font-size:12px; color:#8a97a8; margin-top:4px">BOM 与工艺路线会按这个产品的启用版本自动挂上。</div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">计划数量</label>
                            <input name="plan_qty" type="number" step="0.001" min="0.001" class="form-control" value="{{ old('plan_qty', 50) }}" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">计划开工</label>
                            <input name="plan_start" type="date" class="form-control" value="{{ old('plan_start', now()->toDateString()) }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label">计划完工</label>
                            <input name="plan_end" type="date" class="form-control" value="{{ old('plan_end', now()->addDays(7)->toDateString()) }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label">优先级</label>
                            <select name="priority" class="form-select">
                                @foreach ([1 => '1 特急', 3 => '3 急', 5 => '5 正常', 7 => '7 可延后'] as $v => $t)
                                    <option value="{{ $v }}" @selected(old('priority', 5) == $v)>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-8">
                            <label class="form-label">客户</label>
                            <input name="customer" class="form-control" value="{{ old('customer') }}" placeholder="可留空">
                        </div>
                        <div class="col-12">
                            <label class="form-label">备注</label>
                            <input name="remark" class="form-control" value="{{ old('remark') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                    <button class="btn btn-primary" type="submit">创建草稿</button>
                </div>
            </form>
        </div>
    </div>
@endsection
