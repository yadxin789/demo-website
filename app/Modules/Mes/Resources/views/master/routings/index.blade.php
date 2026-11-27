@extends('mes::layouts.app')

@section('title', '工艺路线')
@section('sub', '产品 → 工序序列 · 标准工时与检验点')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">工艺路线</div>
            <div class="num">{{ $stat['count'] }}</div>
            <div class="hint">覆盖成品与半成品</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">工序总数</div>
            <div class="num">{{ $stat['ops'] }}</div>
            <div class="hint">平均 {{ $stat['count'] > 0 ? round($stat['ops'] / $stat['count'], 1) : 0 }} 道 / 条</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">标准工时合计</div>
            <div class="num">{{ $stat['hours'] }}</div>
            <div class="hint">小时（全部路线相加）</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">检验工序</div>
            <div class="num" style="color:#0f766e">{{ $stat['inspected'] }}</div>
            <div class="hint">下达工单时自动生成检验任务</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap">
            <form method="get" style="display:flex; gap:8px">
                <input name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:240px" placeholder="路线编码 / 产品名称">
                <button class="btn btn-sm btn-outline-secondary">筛选</button>
                @if ($q !== '')
                    <a href="{{ route('mes.master.routings.index') }}" class="btn btn-sm btn-link">重置</a>
                @endif
            </form>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#rtModal" onclick="fillRt(null)">+ 新建工艺路线</button>
        </div>

        <table class="mes-table">
            <thead>
            <tr>
                <th>路线编码</th>
                <th>产品</th>
                <th>版本</th>
                <th class="num">工序数</th>
                <th class="num">总工时</th>
                <th class="num">检验点</th>
                <th>状态</th>
                <th style="width:110px"></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($routings as $r)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $r->code }}</td>
                    <td>
                        {{ $r->product?->name ?? '—' }}
                        <div style="color:#8a97a8; font-size:12px">{{ $r->product?->code }}</div>
                    </td>
                    <td>{{ $r->version }}</td>
                    <td class="num">{{ $r->operations_count }}</td>
                    <td class="num">{{ round((float) $r->operations->sum('std_minutes') / 60, 1) }} h</td>
                    <td class="num">{{ $r->operations->where('inspection_required', true)->count() }}</td>
                    <td>
                        <span class="mes-badge {{ $r->status === 'active' ? 'b-done' : 'b-draft' }}">
                            {{ $r->status === 'active' ? '启用' : '归档' }}
                        </span>
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary py-0 px-2" href="{{ route('mes.master.routings.show', $r) }}">工序</a>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#rtModal"
                                onclick='fillRt(@json($r))'>编辑</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; color:#8a97a8; padding:30px">没有匹配的工艺路线。</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:12px">{{ $routings->links() }}</div>
    </div>

    <div class="modal fade" id="rtModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" id="rtForm" action="{{ route('mes.master.routings.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST" id="rtMethod">
                <div class="modal-header">
                    <h5 class="modal-title" id="rtTitle">新建工艺路线</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
                    @endif
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">路线编码</label>
                            <input name="code" class="form-control" value="{{ old('code') }}" placeholder="RT-FG-009" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">版本</label>
                            <input name="version" class="form-control" value="{{ old('version', 'V1') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">产品（成品 / 半成品）</label>
                            <select name="product_material_id" class="form-select" required>
                                <option value="">请选择</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}" @selected(old('product_material_id') == $p->id)>
                                        {{ $p->code }} · {{ $p->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">状态</label>
                            <select name="status" class="form-select">
                                <option value="active">启用</option>
                                <option value="archived">归档</option>
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
function fillRt(r) {
    const form = document.getElementById('rtForm');
    const method = document.getElementById('rtMethod');
    const title = document.getElementById('rtTitle');

    if (!r) {
        form.action = "{{ route('mes.master.routings.store') }}";
        method.value = 'POST';
        title.textContent = '新建工艺路线';
        form.reset();
        return;
    }
    form.action = "/mes/master/routings/" + r.id;
    method.value = 'PUT';
    title.textContent = '编辑工艺路线 · ' + r.code;
    form.querySelector('[name=code]').value = r.code;
    form.querySelector('[name=version]').value = r.version;
    form.querySelector('[name=product_material_id]').value = r.product_material_id;
    form.querySelector('[name=status]').value = r.status;
}
</script>
@endpush
