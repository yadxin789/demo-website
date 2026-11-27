@extends('mes::layouts.app')

@section('title', '工艺 ' . $routing->code)
@section('sub', ($routing->product?->name ?? '') . ' · ' . $routing->version . ' · 工序序列')

@section('content')
    <div class="mes-card" style="margin-bottom:14px">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap">
            <div>
                <div style="font-size:13px; color:#6b7a90; margin-bottom:6px">
                    <a href="{{ route('mes.master.routings.index') }}" style="color:#0d6efd">← 返回工艺路线</a>
                </div>
                <div style="font-size:20px; font-weight:700">{{ $routing->product?->name ?? '—' }}</div>
                <div style="color:#6b7a90; font-size:13px; margin-top:4px">
                    {{ $routing->product?->code }} ·
                    <span class="mes-badge {{ $routing->status === 'active' ? 'b-done' : 'b-draft' }}">{{ $routing->status === 'active' ? '启用' : '归档' }}</span>
                </div>
            </div>
            <div style="display:flex; gap:24px; text-align:right">
                <div>
                    <div style="font-size:12px; color:#8a97a8">工序数</div>
                    <div style="font-size:22px; font-weight:700">{{ $routing->operations->count() }}</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#8a97a8">总标准工时</div>
                    <div style="font-size:22px; font-weight:700; color:#0f766e">{{ round($total / 60, 2) }} h</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#8a97a8">检验点</div>
                    <div style="font-size:22px; font-weight:700">{{ $routing->operations->where('inspection_required', true)->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="mes-card" style="margin-bottom:14px">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
            <div style="font-weight:600">工序序列</div>
            <div style="display:flex; gap:8px">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rtHeadModal">改头信息</button>
                <form method="post" action="{{ route('mes.master.routings.destroy', $routing) }}"
                      onsubmit="return confirm('删除工艺路线 {{ $routing->code }} 会同时删掉全部工序，确定吗？')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">删除路线</button>
                </form>
            </div>
        </div>

        <table class="mes-table">
            <thead>
            <tr>
                <th class="num">工序号</th>
                <th>工序名称</th>
                <th>工作中心</th>
                <th class="num">标准工时</th>
                <th>检验</th>
                <th style="width:110px"></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($routing->operations as $op)
                <tr>
                    <td class="num">
                        <span class="mes-badge b-draft" style="font-family:ui-monospace,Consolas,monospace">{{ $op->op_no }}</span>
                    </td>
                    <td>{{ $op->op_name }}</td>
                    <td>
                        {{ $op->workCenter?->name ?? '未指定' }}
                        <div style="color:#8a97a8; font-size:12px">{{ $op->workCenter?->code }}</div>
                    </td>
                    <td class="num">{{ (float) $op->std_minutes }} min</td>
                    <td>
                        @if ($op->inspection_required)
                            <span class="mes-badge b-warn">需检验</span>
                        @else
                            <span style="color:#a8b3c1; font-size:12px">—</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#opModal"
                                onclick='fillOp(@json($op))'>改</button>
                        <form method="post" action="{{ route('mes.master.routings.operations.destroy', [$routing, $op]) }}"
                              style="display:inline" onsubmit="return confirm('删除工序 {{ $op->op_no }} {{ $op->op_name }}？')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0 px-2">删</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; color:#8a97a8; padding:26px">
                    还没有工序。用下面的表单把第一道工序加进来。
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mes-card">
        <div style="font-weight:600; margin-bottom:12px">按工作中心汇总</div>
        <table class="mes-table">
            <thead>
            <tr><th>工作中心</th><th class="num">工序数</th><th class="num">工时</th><th style="width:45%">占整条路线比重</th></tr>
            </thead>
            <tbody>
            @foreach ($byCenter as $row)
                <tr>
                    <td>{{ $row['name'] }} <span style="color:#8a97a8; font-size:12px">{{ $row['code'] }}</span></td>
                    <td class="num">{{ $row['ops'] }}</td>
                    <td class="num">{{ round($row['minutes'] / 60, 2) }} h</td>
                    <td>
                        <div style="background:#eef2f7; border-radius:6px; height:8px; overflow:hidden">
                            <div style="height:8px; background:#f08c00; width:{{ $total > 0 ? round($row['minutes'] / $total * 100, 1) : 0 }}%"></div>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div style="margin-top:18px; padding-top:14px; border-top:1px dashed #e2e8f0">
            <div style="font-weight:600; margin-bottom:10px">添加工序</div>
            @if ($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('mes.master.routings.operations.store', $routing) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-1">
                    <label class="form-label" style="font-size:12px">工序号</label>
                    <input name="op_no" type="number" class="form-control form-control-sm" value="{{ old('op_no', ((int) $routing->operations->max('op_no')) + 10) ?: 10 }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px">工序名称</label>
                    <input name="op_name" class="form-control form-control-sm" value="{{ old('op_name') }}" placeholder="整机装配" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px">工作中心</label>
                    <select name="work_center_id" class="form-select form-select-sm">
                        <option value="">未指定</option>
                        @foreach ($centers as $c)
                            <option value="{{ $c->id }}" @selected(old('work_center_id') == $c->id)>{{ $c->code }} · {{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px">标准工时（分钟）</label>
                    <input name="std_minutes" type="number" step="0.01" class="form-control form-control-sm" value="{{ old('std_minutes', 30) }}" required>
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="inspection_required" value="1" id="opInsp" @checked(old('inspection_required'))>
                        <label class="form-check-label" for="opInsp" style="font-size:12px">需检验</label>
                    </div>
                    <button class="btn btn-sm btn-primary w-100 mt-1">加入工序</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="opModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" id="opForm">
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-header">
                    <h5 class="modal-title" id="opTitle">修改工序</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label">工序号</label>
                            <input name="op_no" type="number" class="form-control" required>
                        </div>
                        <div class="col-8">
                            <label class="form-label">工序名称</label>
                            <input name="op_name" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">工作中心</label>
                            <select name="work_center_id" class="form-select">
                                <option value="">未指定</option>
                                @foreach ($centers as $c)
                                    <option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">标准工时（分钟）</label>
                            <input name="std_minutes" type="number" step="0.01" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="inspection_required" value="1" id="opInspEdit">
                                <label class="form-check-label" for="opInspEdit">这道工序需要检验</label>
                            </div>
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

    <div class="modal fade" id="rtHeadModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('mes.master.routings.update', $routing) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">修改路线头信息</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="code" value="{{ $routing->code }}">
                    <input type="hidden" name="product_material_id" value="{{ $routing->product_material_id }}">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">版本</label>
                            <input name="version" class="form-control" value="{{ $routing->version }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">状态</label>
                            <select name="status" class="form-select">
                                <option value="active" @selected($routing->status === 'active')>启用</option>
                                <option value="archived" @selected($routing->status === 'archived')>归档</option>
                            </select>
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
@endsection

@push('scripts')
<script>
function fillOp(o) {
    const form = document.getElementById('opForm');
    const title = document.getElementById('opTitle');
    form.action = "/mes/master/routings/{{ $routing->id }}/operations/" + o.id;
    title.textContent = '修改工序 · ' + o.op_no + ' ' + o.op_name;
    form.querySelector('[name=op_no]').value = o.op_no;
    form.querySelector('[name=op_name]').value = o.op_name;
    form.querySelector('[name=work_center_id]').value = o.work_center_id || '';
    form.querySelector('[name=std_minutes]').value = o.std_minutes;
    document.getElementById('opInspEdit').checked = !!o.inspection_required;
}
</script>
@endpush
