@extends('mes::layouts.app')

@section('title', 'BOM ' . $bom->code)
@section('sub', ($bom->product?->name ?? '') . ' · ' . $bom->version . ' · 单层物料清单')

@section('content')
    <div class="mes-card" style="margin-bottom:14px">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap">
            <div>
                <div style="font-size:13px; color:#6b7a90; margin-bottom:6px">
                    <a href="{{ route('mes.master.boms.index') }}" style="color:#0d6efd">← 返回 BOM 列表</a>
                </div>
                <div style="font-size:20px; font-weight:700">{{ $bom->product?->name ?? '—' }}</div>
                <div style="color:#6b7a90; font-size:13px; margin-top:4px">
                    {{ $bom->product?->code }} · 单位 {{ $bom->product?->unit }} ·
                    <span class="mes-badge {{ $bom->status === 'active' ? 'b-done' : 'b-draft' }}">{{ $bom->status === 'active' ? '启用' : '归档' }}</span>
                </div>
            </div>
            <div style="display:flex; gap:24px; text-align:right">
                <div>
                    <div style="font-size:12px; color:#8a97a8">组件行</div>
                    <div style="font-size:22px; font-weight:700">{{ $lines->count() }}</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#8a97a8">单件料本</div>
                    <div style="font-size:22px; font-weight:700; color:#0f766e">¥{{ number_format($cost, 2) }}</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#8a97a8">缺料项</div>
                    <div style="font-size:22px; font-weight:700; {{ $lines->where('lack', true)->count() > 0 ? 'color:#d6336c' : 'color:#2f9e44' }}">
                        {{ $lines->where('lack', true)->count() }}
                    </div>
                </div>
            </div>
        </div>
        @if ($bom->remark)
            <div style="margin-top:12px; padding:10px 12px; background:#f6f8fb; border-radius:8px; font-size:13px; color:#4a5568">
                备注：{{ $bom->remark }}
            </div>
        @endif
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
            <div style="font-weight:600">组件明细</div>
            <div style="display:flex; gap:8px">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bomHeadModal" onclick="fillHead()">改头信息</button>
                <form method="post" action="{{ route('mes.master.boms.destroy', $bom) }}"
                      onsubmit="return confirm('删除 BOM {{ $bom->code }} 会同时删掉全部组件行，确定吗？')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">删除 BOM</button>
                </form>
            </div>
        </div>

        <table class="mes-table">
            <thead>
            <tr>
                <th class="num">行序</th>
                <th>物料</th>
                <th class="num">单件用量</th>
                <th class="num">损耗率</th>
                <th class="num">需求合计</th>
                <th class="num">单价</th>
                <th class="num">金额</th>
                <th>库存</th>
                <th style="width:110px"></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($lines as $item)
                <tr>
                    <td class="num">{{ $item['line']->seq }}</td>
                    <td>
                        {{ $item['line']->material?->name ?? '—' }}
                        <div style="color:#8a97a8; font-size:12px; font-family:ui-monospace,Consolas,monospace">{{ $item['line']->material?->code }}</div>
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item['line']->qty_per, 4), '0'), '.') }}</td>
                    <td class="num">{{ round((float) $item['line']->loss_rate * 100, 2) }}%</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item['need'], 4), '0'), '.') }} {{ $item['line']->material?->unit }}</td>
                    <td class="num">{{ number_format((float) ($item['line']->material?->std_cost ?? 0), 2) }}</td>
                    <td class="num">{{ number_format($item['amount'], 2) }}</td>
                    <td>
                        @if ($item['lack'])
                            <span class="mes-badge b-bad">库存 {{ rtrim(rtrim(number_format((float) $item['line']->material?->stock_qty, 3), '0'), '.') }}</span>
                        @else
                            <span class="mes-badge b-done">够用</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#lineModal"
                                onclick='fillLine(@json($item["line"]))'>改</button>
                        <form method="post" action="{{ route('mes.master.boms.lines.destroy', [$bom, $item['line']]) }}"
                              style="display:inline" onsubmit="return confirm('删除这行组件？')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0 px-2">删</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center; color:#8a97a8; padding:26px">
                    这个 BOM 还没有组件行。用下面的表单把料加进来。
                </td></tr>
            @endforelse
            </tbody>
            @if ($lines->isNotEmpty())
                <tfoot>
                <tr style="background:#f6f8fb; font-weight:600">
                    <td colspan="6" style="text-align:right">单件料本合计</td>
                    <td class="num" style="color:#0f766e">¥{{ number_format($cost, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
                </tfoot>
            @endif
        </table>

        <div style="margin-top:18px; padding-top:14px; border-top:1px dashed #e2e8f0">
            <div style="font-weight:600; margin-bottom:10px">添加组件</div>
            @if ($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('mes.master.boms.lines.store', $bom) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label" style="font-size:12px">组件物料（原料 / 半成品）</label>
                    <select name="component_material_id" class="form-select form-select-sm" required>
                        <option value="">请选择</option>
                        @foreach ($components as $c)
                            <option value="{{ $c->id }}" @selected(old('component_material_id') == $c->id)>
                                {{ $c->code }} · {{ $c->name }}（{{ $c->unit }}）
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px">单件用量</label>
                    <input name="qty_per" type="number" step="0.0001" min="0.0001" class="form-control form-control-sm" value="{{ old('qty_per', 1) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px">损耗率（% 或 0.02）</label>
                    <input name="loss_rate" class="form-control form-control-sm" value="{{ old('loss_rate', 0) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px">行序（空则自动）</label>
                    <input name="seq" type="number" class="form-control form-control-sm" value="{{ old('seq') }}">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100">加入 BOM</button>
                </div>
            </form>
            <div style="margin-top:8px; font-size:12px; color:#8a97a8">
                需求合计 = 单件用量 ×（1 + 损耗率）。损耗率可以填 2 表示 2%，也可以填 0.02。
            </div>
        </div>
    </div>

    <div class="modal fade" id="lineModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" id="lineForm">
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-header">
                    <h5 class="modal-title" id="lineTitle">修改组件行</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">组件物料</label>
                            <select name="component_material_id" class="form-select" required>
                                @foreach ($components as $c)
                                    <option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label">单件用量</label>
                            <input name="qty_per" type="number" step="0.0001" min="0.0001" class="form-control">
                        </div>
                        <div class="col-4">
                            <label class="form-label">损耗率</label>
                            <input name="loss_rate" class="form-control">
                        </div>
                        <div class="col-4">
                            <label class="form-label">行序</label>
                            <input name="seq" type="number" class="form-control">
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

    <div class="modal fade" id="bomHeadModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('mes.master.boms.update', $bom) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">修改 BOM 头信息</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">版本</label>
                            <input name="version" class="form-control" value="{{ $bom->version }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">状态</label>
                            <select name="status" class="form-select">
                                <option value="active" @selected($bom->status === 'active')>启用</option>
                                <option value="archived" @selected($bom->status === 'archived')>归档</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="code" value="{{ $bom->code }}">
                            <input type="hidden" name="product_material_id" value="{{ $bom->product_material_id }}">
                            <label class="form-label">备注</label>
                            <input name="remark" class="form-control" value="{{ $bom->remark }}">
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
function fillLine(l) {
    const form = document.getElementById('lineForm');
    const title = document.getElementById('lineTitle');
    form.action = "/mes/master/boms/{{ $bom->id }}/lines/" + l.id;
    title.textContent = '修改组件行 · ' + l.component_material_id;
    form.querySelector('[name=component_material_id]').value = l.component_material_id;
    form.querySelector('[name=qty_per]').value = l.qty_per;
    form.querySelector('[name=loss_rate]').value = l.loss_rate;
    form.querySelector('[name=seq]').value = l.seq;
}
</script>
@endpush
