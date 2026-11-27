@extends('mes::layouts.app')

@section('title', 'BOM 物料清单')
@section('sub', '单层 BOM · 产品由哪些料组成、用量与单件成本')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">BOM 总数</div>
            <div class="num">{{ $stat['count'] }}</div>
            <div class="hint">其中 {{ $stat['active'] }} 个启用</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">组件行</div>
            <div class="num">{{ $stat['lines'] }}</div>
            <div class="hint">平均 {{ $stat['count'] > 0 ? round($stat['lines'] / $stat['count'], 1) : 0 }} 行 / BOM</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">原料覆盖</div>
            <div class="num">{{ $products->count() }}</div>
            <div class="hint">可建 BOM 的成品 / 半成品</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">缺料 BOM</div>
            <div class="num" style="color:#d6336c">{{ $stat['short'] }}</div>
            <div class="hint">库存不足一个单位用量</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap">
            <form method="get" style="display:flex; gap:8px; flex-wrap:wrap">
                <input name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:220px" placeholder="BOM 编码 / 产品名称">
                <select name="status" class="form-select form-select-sm" style="width:120px">
                    <option value="">全部状态</option>
                    <option value="active" @selected($status === 'active')>启用</option>
                    <option value="archived" @selected($status === 'archived')>归档</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary">筛选</button>
                <a href="{{ route('mes.master.boms.index') }}" class="btn btn-sm btn-link">重置</a>
            </form>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bomModal" onclick="fillBom(null)">+ 新建 BOM</button>
        </div>

        <table class="mes-table">
            <thead>
            <tr>
                <th>BOM 编码</th>
                <th>产品</th>
                <th>版本</th>
                <th class="num">组件行</th>
                <th class="num">单件料本（元）</th>
                <th>缺料</th>
                <th>状态</th>
                <th style="width:110px"></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $r['bom']->code }}</td>
                    <td>
                        {{ $r['bom']->product?->name ?? '—' }}
                        <div style="color:#8a97a8; font-size:12px">{{ $r['bom']->product?->code }}</div>
                    </td>
                    <td>{{ $r['bom']->version }}</td>
                    <td class="num">{{ $r['bom']->lines_count }}</td>
                    <td class="num">{{ number_format($r['cost'], 2) }}</td>
                    <td>
                        @if ($r['short'] > 0)
                            <span class="mes-badge b-bad">{{ $r['short'] }} 项不足</span>
                        @else
                            <span class="mes-badge b-done">齐套</span>
                        @endif
                    </td>
                    <td>
                        <span class="mes-badge {{ $r['bom']->status === 'active' ? 'b-done' : 'b-draft' }}">
                            {{ $r['bom']->status === 'active' ? '启用' : '归档' }}
                        </span>
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary py-0 px-2" href="{{ route('mes.master.boms.show', $r['bom']) }}">明细</a>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#bomModal"
                                onclick='fillBom(@json($r["bom"]))'>编辑</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; color:#8a97a8; padding:30px">没有匹配的 BOM。</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:12px">{{ $boms->links() }}</div>
    </div>

    <div class="modal fade" id="bomModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" id="bomForm" action="{{ route('mes.master.boms.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST" id="bomMethod">
                <div class="modal-header">
                    <h5 class="modal-title" id="bomTitle">新建 BOM</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
                    @endif
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">BOM 编码</label>
                            <input name="code" class="form-control" value="{{ old('code') }}" placeholder="BOM-FG-009" required>
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
                                        {{ $p->code }} · {{ $p->name }}（{{ $p->category === 'finished' ? '成品' : '半成品' }}）
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
                        <div class="col-6">
                            <label class="form-label">备注</label>
                            <input name="remark" class="form-control" value="{{ old('remark') }}">
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
function fillBom(b) {
    const form = document.getElementById('bomForm');
    const method = document.getElementById('bomMethod');
    const title = document.getElementById('bomTitle');

    if (!b) {
        form.action = "{{ route('mes.master.boms.store') }}";
        method.value = 'POST';
        title.textContent = '新建 BOM';
        form.reset();
        return;
    }
    form.action = "/mes/master/boms/" + b.id;
    method.value = 'PUT';
    title.textContent = '编辑 BOM · ' + b.code;
    form.querySelector('[name=code]').value = b.code;
    form.querySelector('[name=version]').value = b.version;
    form.querySelector('[name=product_material_id]').value = b.product_material_id;
    form.querySelector('[name=status]').value = b.status;
    form.querySelector('[name=remark]').value = b.remark || '';
}
</script>
@endpush
