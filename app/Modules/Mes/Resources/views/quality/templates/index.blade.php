@extends('mes::layouts.app')

@section('title', '检验模板')
@section('sub', '定义每道检验要看哪些项、标准是多少')

@section('content')
    <div class="mes-card" style="margin-bottom:14px">
        <div style="font-size:13px; color:#4a5568; line-height:1.9">
            判定规则：<b>实测值为空 → 不合格</b>；配了上下限 → 按数值区间比；标准值是纯数字 → 必须等于该值；
            配了「合格样本」→ 按文本比对；都没有 → 记录实测值并按人工判定合格。
            <br>模板改了会影响之后执行的检验单，已经判定过的历史单不改写。
        </div>
    </div>

    @foreach ($templates as $t)
        <div class="mes-card" style="margin-bottom:14px">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px">
                <div>
                    <span class="mes-badge {{ $t->type === 'IQC' ? 'b-draft' : ($t->type === 'IPQC' ? 'b-progress' : 'b-released') }}">{{ $t->type }}</span>
                    <span style="font-family:ui-monospace,Consolas,monospace; font-size:13px; margin-left:8px">{{ $t->code }}</span>
                    <b style="margin-left:8px">{{ $t->name }}</b>
                <span style="font-size:12px; color:#8a97a8; margin-left:8px">
                    {{ $t->op_no ? '绑定工序 ' . $t->op_no : '整单检验' }} · {{ count((array) $t->items) }} 项
                    · 已用于 {{ $usage[$t->id] ?? 0 }} 张检验单
                </span>
                </div>
            </div>

            <form method="post" action="{{ route('mes.quality.templates.update', $t) }}">
                @csrf
                @method('PUT')

                <div class="row g-2" style="margin-bottom:10px">
                    <div class="col-md-3">
                        <label class="form-label" style="font-size:12px">模板编码</label>
                        <input name="code" class="form-control form-control-sm" value="{{ $t->code }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:12px">模板名称</label>
                        <input name="name" class="form-control form-control-sm" value="{{ $t->name }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" style="font-size:12px">类型</label>
                        <select name="type" class="form-select form-select-sm">
                            @foreach (['IQC', 'IPQC', 'OQC'] as $ty)
                                <option value="{{ $ty }}" @selected($t->type === $ty)>{{ $ty }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" style="font-size:12px">绑定工序号</label>
                        <input name="op_no" type="number" min="0" class="form-control form-control-sm" value="{{ $t->op_no }}">
                    </div>
                </div>

                <table class="mes-table" style="font-size:13px template-rows">
                    <thead>
                    <tr>
                        <th style="width:22%">检验项</th>
                        <th style="width:20%">标准描述</th>
                        <th style="width:11%">下限</th>
                        <th style="width:11%">上限</th>
                        <th style="width:9%">单位</th>
                        <th style="width:19%">合格样本</th>
                        <th style="width:8%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ((array) $t->items as $idx => $item)
                        <tr>
                            <td><input name="items[{{ $idx }}][name]" class="form-control form-control-sm" value="{{ $item['name'] ?? '' }}"></td>
                            <td><input name="items[{{ $idx }}][std]" class="form-control form-control-sm" value="{{ $item['std'] ?? '' }}"></td>
                            <td><input name="items[{{ $idx }}][min]" type="number" step="0.001" class="form-control form-control-sm" value="{{ $item['min'] ?? '' }}"></td>
                            <td><input name="items[{{ $idx }}][max]" type="number" step="0.001" class="form-control form-control-sm" value="{{ $item['max'] ?? '' }}"></td>
                            <td><input name="items[{{ $idx }}][unit]" class="form-control form-control-sm" value="{{ $item['unit'] ?? '' }}"></td>
                            <td><input name="items[{{ $idx }}][expect]" class="form-control form-control-sm" value="{{ $item['expect'] ?? '' }}"></td>
                            <td><button type="button" class="btn btn-sm btn-link" onclick="this.closest('tr').remove()">删除</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addRow(this)">+ 加一项</button>
                    <button class="btn btn-sm btn-primary">保存模板</button>
                </div>
            </form>
        </div>
    @endforeach

    <div class="mes-card">
        <div style="font-weight:600; margin-bottom:10px">新建模板</div>
        <form method="post" action="{{ route('mes.quality.templates.store') }}">
            @csrf
            <div class="row g-2" style="margin-bottom:10px">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px">模板编码</label>
                    <input name="code" class="form-control form-control-sm" placeholder="如 OQC-PACK" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" style="font-size:12px">模板名称</label>
                    <input name="name" class="form-control form-control-sm" placeholder="如 包装外观检验" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px">类型</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="IQC">IQC</option>
                        <option value="IPQC" selected>IPQC</option>
                        <option value="OQC">OQC</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px">绑定工序号</label>
                    <input name="op_no" type="number" min="0" class="form-control form-control-sm">
                </div>
            </div>
            <table class="mes-table" style="font-size:13px">
                <thead>
                <tr>
                    <th style="width:22%">检验项</th>
                    <th style="width:20%">标准描述</th>
                    <th style="width:11%">下限</th>
                    <th style="width:11%">上限</th>
                    <th style="width:9%">单位</th>
                    <th style="width:19%">合格样本</th>
                    <th style="width:8%"></th>
                </tr>
                </thead>
                <tbody>
                @for ($i = 0; $i < 2; $i++)
                    <tr>
                        <td><input name="items[{{ $i }}][name]" class="form-control form-control-sm" placeholder="检验项名称"></td>
                        <td><input name="items[{{ $i }}][std]" class="form-control form-control-sm"></td>
                        <td><input name="items[{{ $i }}][min]" type="number" step="0.001" class="form-control form-control-sm"></td>
                        <td><input name="items[{{ $i }}][max]" type="number" step="0.001" class="form-control form-control-sm"></td>
                        <td><input name="items[{{ $i }}][unit]" class="form-control form-control-sm"></td>
                        <td><input name="items[{{ $i }}][expect]" class="form-control form-control-sm"></td>
                        <td><button type="button" class="btn btn-sm btn-link" onclick="this.closest('tr').remove()">删除</button></td>
                    </tr>
                @endfor
                </tbody>
            </table>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addRow(this)">+ 加一项</button>
                <button class="btn btn-sm btn-success">创建模板</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
function addRow(btn) {
    const table = btn.closest('form').querySelector('tbody');
    const tpl = table.rows[table.rows.length - 1];
    const next = tpl.cloneNode(true);
    const idx = table.rows.length;
    next.querySelectorAll('input').forEach(function (input) {
        input.value = '';
        input.name = input.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
        if (input.hasAttribute('required')) input.removeAttribute('required');
    });
    table.appendChild(next);
}
</script>
@endpush
