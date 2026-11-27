@extends('mes::layouts.app')

@section('title', '不良记录')
@section('sub', '不合格品的数量、原因与处置：返工还是报废')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi {{ $stat['open'] > 0 ? 'bad' : '' }}">
            <div class="lbl">未结案</div>
            <div class="num">{{ $stat['open'] }}</div>
            <div class="hint">还没处置完</div>
        </div>
        <div class="mes-kpi orange">
            <div class="lbl">待返工数量</div>
            <div class="num">{{ rtrim(rtrim(number_format($stat['qty'], 3), '0'), '.') }}</div>
            <div class="hint">件（未结案合计）</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">返工处置</div>
            <div class="num">{{ $stat['rework'] }}</div>
            <div class="hint">条记录</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">报废处置</div>
            <div class="num">{{ $stat['scrap'] }}</div>
            <div class="hint">条记录</div>
        </div>
    </div>

    <div class="mes-card" style="margin-bottom:14px">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px">
            <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
                <input name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:190px" placeholder="不良代码 / 工单号">
                <select name="status" class="form-select form-select-sm" style="width:120px">
                    <option value="">全部状态</option>
                    <option value="open" @selected($status === 'open')>未处置</option>
                    <option value="handling" @selected($status === 'handling')>处置中</option>
                    <option value="closed" @selected($status === 'closed')>已结案</option>
                </select>
                <select name="disposal" class="form-select form-select-sm" style="width:120px">
                    <option value="">全部处置</option>
                    <option value="rework" @selected($disposal === 'rework')>返工</option>
                    <option value="scrap" @selected($disposal === 'scrap')>报废</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary">查询</button>
            </form>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#defectModal">+ 登记不良</button>
        </div>

        <table class="mes-table" style="font-size:13px">
            <thead>
            <tr>
                <th>不良代码</th>
                <th>工单 / 产品</th>
                <th>工序</th>
                <th class="num">数量</th>
                <th>处置</th>
                <th>原因</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($defects as $d)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $d->defect_code }}</td>
                    <td>
                        <a href="{{ route('mes.work-orders.show', $d->work_order_id) }}" style="color:#16233f">{{ $d->workOrder?->order_no }}</a>
                        <div style="color:#8a97a8; font-size:12px">{{ $d->workOrder?->product?->name }}</div>
                    </td>
                    <td style="color:#6b7a90">{{ $d->operation ? $d->operation->op_no . ' ' . $d->operation->op_name : '—' }}</td>
                    <td class="num" style="color:#c92a2a">{{ rtrim(rtrim(number_format((float) $d->qty, 3), '0'), '.') }}</td>
                    <td>
                        <span class="mes-badge {{ $d->disposal === 'rework' ? 'b-progress' : 'b-bad' }}">
                            {{ $d->disposal === 'rework' ? '返工' : '报废' }}
                        </span>
                    </td>
                    <td style="color:#6b7a90; font-size:12px">{{ $d->cause ?: '—' }}</td>
                    <td>
                        <span class="mes-badge {{ $d->status === 'closed' ? 'b-done' : ($d->status === 'handling' ? 'b-progress' : 'b-draft') }}">
                            {{ ['open' => '未处置', 'handling' => '处置中', 'closed' => '已结案'][$d->status] ?? $d->status }}
                        </span>
                    </td>
                    <td>
                        @if ($d->status !== 'closed')
                            <form method="post" action="{{ route('mes.quality.defects.rework', $d) }}" style="display:inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary" onclick="return confirm('按这张工单的产品与不良数量建一张返工工单？')">转返工</button>
                            </form>
                            <form method="post" action="{{ route('mes.quality.defects.update', $d) }}" style="display:inline">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="disposal" value="{{ $d->disposal }}">
                                <input type="hidden" name="status" value="closed">
                                <button class="btn btn-sm btn-link">结案</button>
                            </form>
                        @else
                            <span style="color:#a8b3c1; font-size:12px">已处理</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="mes-empty">还没有不良记录。检验判定不合格后到这里登记处置。</div></td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:14px">{{ $defects->links() }}</div>
    </div>

    <div class="modal fade" id="defectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="post" action="{{ route('mes.quality.defects.store') }}">
                @csrf
                <div class="modal-header">
                    <div class="modal-title" style="font-weight:600">登记不良</div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px">工单</label>
                            <select name="work_order_id" class="form-select form-select-sm" required>
                                <option value="">选择工单</option>
                                @foreach ($orders as $o)
                                    <option value="{{ $o->id }}">{{ $o->order_no }} · {{ $o->product?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:12px">不良代码</label>
                            <select name="defect_code" class="form-select form-select-sm" required>
                                @foreach ($codes as $code => $label)
                                    <option value="{{ $code }}">{{ $code }} · {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:12px">数量</label>
                            <input name="qty" type="number" step="0.001" min="0.001" class="form-control form-control-sm" value="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px">处置方式</label>
                            <select name="disposal" class="form-select form-select-sm">
                                <option value="rework">返工</option>
                                <option value="scrap">报废</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px">原因</label>
                            <input name="cause" class="form-control form-control-sm" placeholder="如：炉温偏低导致虚焊">
                        </div>
                    </div>
                    <div style="font-size:12px; color:#8a97a8; margin-top:10px">
                        登记后状态为「未处置」；点「转返工」会按原工单产品与数量自动建一张返工工单。
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                    <button class="btn btn-sm btn-primary">登记</button>
                </div>
            </form>
        </div>
    </div>
@endsection
