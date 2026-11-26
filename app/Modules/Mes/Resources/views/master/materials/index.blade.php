@extends('mes::layouts.app')

@section('title', '物料主数据')
@section('sub', 'MJ- 编码 · 原料 / 半成品 / 成品')

@section('content')
    <div class="mes-kpis" style="margin-bottom:16px">
        <div class="mes-kpi"><div class="lb">物料总数</div><div class="vl">{{ $stats['total'] }}</div></div>
        <div class="mes-kpi ok"><div class="lb">成品</div><div class="vl">{{ $stats['finished'] }}</div></div>
        <div class="mes-kpi"><div class="lb">半成品</div><div class="vl">{{ $stats['semi'] }}</div></div>
        <div class="mes-kpi"><div class="lb">原料</div><div class="vl">{{ $stats['raw'] }}</div></div>
        <div class="mes-kpi bad"><div class="lb">低于安全库存</div><div class="vl">{{ $stats['low'] }}</div></div>
    </div>

    <div class="mes-card">
        <div style="display:flex; gap:10px; align-items:center; justify-content:space-between; margin-bottom:14px">
            <form method="get" class="d-flex gap-2" style="flex:1">
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="编码或名称" style="max-width:260px">
                <select name="category" class="form-select form-select-sm" style="max-width:150px">
                    <option value="">全部分类</option>
                    <option value="finished" @selected($category === 'finished')>成品</option>
                    <option value="semi" @selected($category === 'semi')>半成品</option>
                    <option value="raw" @selected($category === 'raw')>原料</option>
                </select>
                <button class="btn btn-sm btn-dark">筛选</button>
                @if ($q !== '' || $category !== '')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.master.materials.index') }}">清空</a>
                @endif
            </form>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#materialModal"
                    onclick="fillMaterial(null)">+ 新增物料</button>
        </div>

        @if ($materials->isEmpty())
            <div class="mes-empty">没有匹配的物料。</div>
        @else
            <table class="mes-table">
                <thead>
                <tr>
                    <th>编码</th>
                    <th>名称</th>
                    <th>规格</th>
                    <th>分类</th>
                    <th>单位</th>
                    <th class="num">安全库存</th>
                    <th class="num">现有库存</th>
                    <th class="num">标准成本</th>
                    <th>状态</th>
                    <th style="width:70px"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($materials as $m)
                    <tr>
                        <td style="font-family:ui-monospace,Consolas,monospace">{{ $m->code }}</td>
                        <td>{{ $m->name }}</td>
                        <td style="color:#6b7a90">{{ $m->spec ?: '—' }}</td>
                        <td>
                            @php
                                $catLabel = ['finished' => '成品', 'semi' => '半成品', 'raw' => '原料'][$m->category] ?? $m->category;
                            @endphp
                            <span class="mes-badge {{ $m->category === 'finished' ? 'b-done' : ($m->category === 'semi' ? 'b-released' : 'b-draft') }}">{{ $catLabel }}</span>
                        </td>
                        <td>{{ $m->unit }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $m->safety_stock, 0), '.'), '.') }}</td>
                        <td class="num">
                            @if ((float) $m->stock_qty < (float) $m->safety_stock)
                                <span style="color:#d6336c; font-weight:600">{{ rtrim(rtrim(number_format((float) $m->stock_qty, 0), '.'), '.') }}</span>
                            @else
                                {{ rtrim(rtrim(number_format((float) $m->stock_qty, 0), '.'), '.') }}
                            @endif
                        </td>
                        <td class="num">{{ number_format((float) $m->std_cost, 2) }}</td>
                        <td>
                            <span class="mes-badge {{ $m->is_active ? 'b-done' : 'b-draft' }}">{{ $m->is_active ? '启用' : '停用' }}</span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                    data-bs-toggle="modal" data-bs-target="#materialModal"
                                    onclick='fillMaterial(@json($m))'>编辑</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div style="margin-top:12px">{{ $materials->links() }}</div>
        @endif
    </div>

    <!-- 新增 / 编辑 模态框 -->
    <div class="modal fade" id="materialModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="post" id="materialForm" action="{{ route('mes.master.materials.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST" id="materialMethod">
                <div class="modal-header">
                    <h5 class="modal-title" id="materialModalTitle">新增物料</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">物料编码</label>
                            <input name="code" class="form-control" value="{{ old('code') }}" placeholder="MJ-FG-001" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">名称</label>
                            <input name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">规格</label>
                            <input name="spec" class="form-control" value="{{ old('spec') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">分类</label>
                            <select name="category" class="form-select" required>
                                <option value="finished">成品</option>
                                <option value="semi">半成品</option>
                                <option value="raw">原料</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">单位</label>
                            <input name="unit" class="form-control" value="{{ old('unit', 'PCS') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">安全库存</label>
                            <input name="safety_stock" type="number" step="0.001" class="form-control" value="{{ old('safety_stock', 0) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">现有库存</label>
                            <input name="stock_qty" type="number" step="0.001" class="form-control" value="{{ old('stock_qty', 0) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">标准成本</label>
                            <input name="std_cost" type="number" step="0.0001" class="form-control" value="{{ old('std_cost', 0) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">备注</label>
                            <textarea name="remark" class="form-control" rows="2">{{ old('remark') }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="mActive" checked>
                                <label class="form-check-label" for="mActive">启用</label>
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
@endsection

@push('scripts')
<script>
function fillMaterial(m) {
    const form = document.getElementById('materialForm');
    const method = document.getElementById('materialMethod');
    const title = document.getElementById('materialModalTitle');

    if (!m) {
        form.action = "{{ route('mes.master.materials.store') }}";
        method.value = 'POST';
        title.textContent = '新增物料';
        form.reset();
        return;
    }

    form.action = "/mes/master/materials/" + m.id;
    method.value = 'PUT';
    title.textContent = '编辑物料 · ' + m.code;

    form.querySelector('[name=code]').value = m.code;
    form.querySelector('[name=name]').value = m.name;
    form.querySelector('[name=spec]').value = m.spec || '';
    form.querySelector('[name=category]').value = m.category;
    form.querySelector('[name=unit]').value = m.unit;
    form.querySelector('[name=safety_stock]').value = m.safety_stock;
    form.querySelector('[name=stock_qty]').value = m.stock_qty;
    form.querySelector('[name=std_cost]').value = m.std_cost;
    form.querySelector('[name=remark]').value = m.remark || '';
    form.querySelector('[name=is_active]').checked = !!m.is_active;
}
</script>
@endpush
