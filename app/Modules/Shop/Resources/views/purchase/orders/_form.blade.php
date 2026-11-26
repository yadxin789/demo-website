@csrf
@isset($method)
    @method($method)
@endisset

@php $order = $order ?? null; @endphp

@php
    $oldLines = old('lines', isset($order) && $order->lines->isNotEmpty()
        ? $order->lines->map(fn ($l) => [
            'product_id' => $l->product_id,
            'qty' => (float) $l->qty,
            'unit_price' => (float) $l->unit_price,
            'discount_rate' => (float) $l->discount_rate,
            'tax_id' => $l->tax_id,
            'line_note' => $l->line_note,
        ])->values()->toArray()
        : [['product_id' => '', 'qty' => '', 'unit_price' => '', 'discount_rate' => 0, 'tax_id' => '', 'line_note' => '']]);
@endphp

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <label for="supplier_id" class="form-label">供应商 <span aria-hidden="true">*</span></label>
        <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
            <option value="">请选择供应商</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected(old('supplier_id', $order?->supplier_id ?? '') == $supplier->id)>
                    {{ $supplier->code }} · {{ $supplier->name }}
                </option>
            @endforeach
        </select>
        @error('supplier_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="warehouse_id" class="form-label">入库仓库 <span aria-hidden="true">*</span></label>
        <select id="warehouse_id" name="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
            <option value="">请选择仓库</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $order?->warehouse_id ?? '') == $warehouse->id)>
                    {{ $warehouse->code }} · {{ $warehouse->name }}
                </option>
            @endforeach
        </select>
        @error('warehouse_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-2">
        <label for="order_date" class="form-label">订单日期</label>
        <input type="date" id="order_date" name="order_date" value="{{ old('order_date', optional($order->order_date ?? null)->format('Y-m-d') ?? date('Y-m-d')) }}" class="form-control @error('order_date') is-invalid @enderror">
        @error('order_date')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-2">
        <label for="expected_date" class="form-label">预计到货</label>
        <input type="date" id="expected_date" name="expected_date" value="{{ old('expected_date', optional($order->expected_date ?? null)->format('Y-m-d')) }}" class="form-control @error('expected_date') is-invalid @enderror">
        @error('expected_date')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label for="note" class="form-label">备注</label>
    <textarea id="note" name="note" rows="2" maxlength="255" class="form-control @error('note') is-invalid @enderror">{{ old('note', $order?->note ?? '') }}</textarea>
    @error('note')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="card p-0 mb-3">
    <div class="table-wrapper">
        <table class="table table-hover mb-0" id="po-line-table">
            <caption class="visually-hidden">采购单行明细</caption>
            <thead>
                <tr>
                    <th scope="col" style="inline-size:34%">商品</th>
                    <th scope="col" class="text-end">数量</th>
                    <th scope="col" class="text-end">单价</th>
                    <th scope="col" class="text-end">折扣率%</th>
                    <th scope="col">税率</th>
                    <th scope="col">备注</th>
                    <th scope="col" class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($oldLines as $index => $line)
                    <tr class="po-line">
                        <td>
                            <select name="lines[{{ $index }}][product_id]" class="form-select form-select-sm" required>
                                <option value="">请选择商品</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected($line['product_id'] == $product->id)>
                                        {{ $product->code }} · {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="text-end">
                            <input type="number" step="0.0001" min="0.0001" name="lines[{{ $index }}][qty]" value="{{ $line['qty'] }}" class="form-control form-control-sm text-end" style="inline-size:6rem;">
                        </td>
                        <td class="text-end">
                            <input type="number" step="0.0001" min="0" name="lines[{{ $index }}][unit_price]" value="{{ $line['unit_price'] }}" class="form-control form-control-sm text-end" style="inline-size:6rem;">
                        </td>
                        <td class="text-end">
                            <input type="number" step="0.01" min="0" max="100" name="lines[{{ $index }}][discount_rate]" value="{{ $line['discount_rate'] }}" class="form-control form-control-sm text-end" style="inline-size:5rem;">
                        </td>
                        <td>
                            <select name="lines[{{ $index }}][tax_id]" class="form-select form-select-sm">
                                <option value="">无税</option>
                                @foreach ($taxes as $tax)
                                    <option value="{{ $tax->id }}" @selected($line['tax_id'] == $tax->id)>{{ $tax->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="text" maxlength="255" name="lines[{{ $index }}][line_note]" value="{{ $line['line_note'] }}" class="form-control form-control-sm">
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger po-line-remove" aria-label="删除此行">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="po-line-add">
    <i class="fa-solid fa-plus me-1"></i>添加一行
</button>

@error('lines')<div class="invalid-feedback d-block mb-2" role="alert">{{ $message }}</div>@enderror

@push('scripts')
<script>
    (function () {
        var table = document.getElementById('po-line-table');
        var addBtn = document.getElementById('po-line-add');
        if (!table || !addBtn) return;

        function renumber() {
            var rows = table.querySelectorAll('tbody .po-line');
            rows.forEach(function (row, i) {
                row.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + i + ']');
                });
            });
        }

        addBtn.addEventListener('click', function () {
            var first = table.querySelector('tbody .po-line');
            var clone = first.cloneNode(true);
            clone.querySelectorAll('input').forEach(function (el) { el.value = ''; });
            clone.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
            table.querySelector('tbody').appendChild(clone);
            renumber();
        });

        table.addEventListener('click', function (e) {
            var btn = e.target.closest('.po-line-remove');
            if (!btn) return;
            var rows = table.querySelectorAll('tbody .po-line');
            if (rows.length > 1) {
                btn.closest('tr').remove();
                renumber();
            }
        });
    })();
</script>
@endpush
