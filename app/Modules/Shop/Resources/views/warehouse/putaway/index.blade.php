@extends('shop::layouts.app')

@section('title', '上架 / 库位调拨')
@section('heading', '上架 / 库位调拨')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">扫码上架</h2>

                    <div class="mb-3">
                        <label for="putaway-product-scan" class="form-label">商品扫码</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                            <input type="text" id="putaway-product-scan" class="form-control" autocomplete="off"
                                   placeholder="扫描商品条码 / 二维码">
                            <button type="button" class="btn btn-outline-secondary" data-camera-target="putaway-product-scan">
                                <i class="fa-solid fa-camera"></i><span class="visually-hidden">摄像头扫码</span>
                            </button>
                        </div>
                    </div>

                    <form method="post" action="{{ route('shop.warehouse.putaway.store') }}" id="putaway-form">
                        @csrf
                        <div class="mb-3">
                            <label for="warehouse_id" class="form-label">仓库 <span aria-hidden="true">*</span></label>
                            <select id="warehouse_id" name="warehouse_id" class="form-select" required>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $warehouses->first()?->id) === $warehouse->id)>
                                        {{ $warehouse->code }} · {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="product_id" class="form-label">商品 <span aria-hidden="true">*</span></label>
                            <select id="product_id" name="product_id" class="form-select" required>
                                <option value="">请选择或扫码</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}"
                                            data-traceability="{{ $product->traceability }}"
                                            data-barcode="{{ $product->barcode }}"
                                            @selected((int) old('product_id') === $product->id)>
                                        {{ $product->code }} · {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label for="from_location_id" class="form-label">源库位 <span aria-hidden="true">*</span></label>
                                <div class="input-group input-group-sm mb-1">
                                    <input type="text" class="form-control" id="putaway-from-scan"
                                           placeholder="扫源库位码" autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary" data-camera-target="putaway-from-scan">
                                        <i class="fa-solid fa-camera"></i><span class="visually-hidden">摄像头扫码</span>
                                    </button>
                                </div>
                                <select id="from_location_id" name="from_location_id" class="form-select form-select-sm" required>
                                    <option value="">请选择</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" data-warehouse="{{ $location->warehouse_id }}"
                                                @selected((int) old('from_location_id') === $location->id)>
                                            {{ $location->code }} · {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label for="to_location_id" class="form-label">目标库位 <span aria-hidden="true">*</span></label>
                                <div class="input-group input-group-sm mb-1">
                                    <input type="text" class="form-control" id="putaway-to-scan"
                                           placeholder="扫目标库位码" autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary" data-camera-target="putaway-to-scan">
                                        <i class="fa-solid fa-camera"></i><span class="visually-hidden">摄像头扫码</span>
                                    </button>
                                </div>
                                <select id="to_location_id" name="to_location_id" class="form-select form-select-sm" required>
                                    <option value="">请选择</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" data-warehouse="{{ $location->warehouse_id }}"
                                                @selected((int) old('to_location_id') === $location->id)>
                                            {{ $location->code }} · {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label for="qty" class="form-label">数量 <span aria-hidden="true">*</span></label>
                                <input type="number" step="0.0001" min="0.0001" id="qty" name="qty"
                                       value="{{ old('qty', 1) }}" class="form-control" required>
                            </div>
                            <div class="col-sm-6">
                                <label for="movement_date" class="form-label">日期</label>
                                <input type="date" id="movement_date" name="movement_date"
                                       value="{{ old('movement_date', now()->format('Y-m-d')) }}" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3 d-none" id="lot-field">
                            <label for="lot_no" class="form-label">批次号</label>
                            <div class="input-group">
                                <input type="text" id="lot_no" name="lot_no" maxlength="50" value="{{ old('lot_no') }}" class="form-control" placeholder="批次追踪商品必填">
                                <button type="button" class="btn btn-outline-secondary" data-camera-target="lot_no">
                                    <i class="fa-solid fa-camera"></i><span class="visually-hidden">扫描批次</span>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 d-none" id="serial-field">
                            <label for="serial_no" class="form-label">序列号</label>
                            <div class="input-group">
                                <input type="text" id="serial_no" name="serial_no" maxlength="50" value="{{ old('serial_no') }}" class="form-control" placeholder="序列号追踪商品必填">
                                <button type="button" class="btn btn-outline-secondary" data-camera-target="serial_no">
                                    <i class="fa-solid fa-camera"></i><span class="visually-hidden">扫描序列号</span>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="note" class="form-label">备注</label>
                            <input type="text" id="note" name="note" maxlength="255" value="{{ old('note') }}" class="form-control">
                        </div>

                        <div id="putaway-status" class="small text-muted mb-2" role="status" aria-live="polite">
                            扫描商品与库位后提交。
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>确认上架</button>
                            <a href="{{ route('shop.locations.labels.index') }}" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-print me-1"></i>打印库位标签
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">最近上架流水</h2>
                        <a href="{{ route('shop.movements.index') }}" class="btn btn-sm btn-outline-secondary">全部流水</a>
                    </div>
                    <div class="table-wrapper">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <caption class="visually-hidden">最近上架流水</caption>
                            <thead>
                                <tr>
                                    <th scope="col">时间</th>
                                    <th scope="col">商品</th>
                                    <th scope="col">目标库位</th>
                                    <th scope="col" class="text-end">数量</th>
                                    <th scope="col">批次 / 序列号</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($movements as $movement)
                                    <tr>
                                        <td>{{ $movement->movement_date?->format('m-d H:i') ?? $movement->created_at?->format('m-d H:i') }}</td>
                                        <td>
                                            {{ $movement->product?->code ?? '—' }}
                                            <span class="text-muted small d-block">{{ $movement->product?->name }}</span>
                                        </td>
                                        <td>{{ $movement->location?->code ?? '—' }}</td>
                                        <td class="text-end">{{ number_format((float) $movement->qty, 4) }}</td>
                                        <td>
                                            {{ $movement->lot_no ?: '—' }}
                                            @if ($movement->serial_no)
                                                <span class="text-muted small d-block">{{ $movement->serial_no }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">暂无上架流水</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            'use strict';

            var statusEl = document.getElementById('putaway-status');
            var warehouse = document.getElementById('warehouse_id');
            var product = document.getElementById('product_id');
            var fromLocation = document.getElementById('from_location_id');
            var toLocation = document.getElementById('to_location_id');
            var qty = document.getElementById('qty');
            var lotField = document.getElementById('lot-field');
            var serialField = document.getElementById('serial-field');
            var lotInput = document.getElementById('lot_no');
            var serialInput = document.getElementById('serial_no');

            function setStatus(message, error) {
                statusEl.textContent = message;
                statusEl.className = 'small ' + (error ? 'text-danger' : 'text-muted') + ' mb-2';
            }

            function filterLocations() {
                var warehouseId = warehouse.value;
                [fromLocation, toLocation].forEach(function (select) {
                    Array.prototype.forEach.call(select.options, function (option) {
                        if (!option.value) { return; }
                        var visible = option.dataset.warehouse === warehouseId;
                        option.hidden = !visible;
                        option.disabled = !visible;
                    });
                    if (select.selectedOptions[0] && select.selectedOptions[0].disabled) { select.value = ''; }
                });
            }

            function syncTraceability() {
                var option = product.selectedOptions[0];
                var traceability = option ? option.dataset.traceability : 'none';
                lotField.classList.toggle('d-none', traceability !== 'lot');
                serialField.classList.toggle('d-none', traceability !== 'serial');
                if (traceability === 'serial') {
                    qty.value = '1';
                    qty.readOnly = true;
                    serialInput.required = true;
                } else {
                    qty.readOnly = false;
                    serialInput.required = false;
                }
                lotInput.required = traceability === 'lot';
            }

            function selectLocation(select, id, label) {
                var option = Array.prototype.find.call(select.options, function (item) {
                    return Number(item.value) === Number(id) && !item.disabled;
                });
                if (!option) {
                    setStatus('库位 ' + label + ' 不属于当前仓库。', true);
                    return false;
                }
                select.value = String(id);
                return true;
            }

            function handleScan(kind, code) {
                window.ErpScan.resolve(code, warehouse.value).then(function (result) {
                    if (kind === 'product') {
                        if (result.type !== 'product') {
                            setStatus('请扫描商品条码或商品二维码。', true);
                            return;
                        }
                        var option = Array.prototype.find.call(product.options, function (item) {
                            return Number(item.value) === Number(result.data.id);
                        });
                        if (!option) {
                            setStatus('商品不在可选列表。', true);
                            return;
                        }
                        product.value = String(result.data.id);
                        syncTraceability();
                        setStatus('已选择商品 ' + result.data.code + '。');
                        return;
                    }

                    if (kind === 'from' || kind === 'to') {
                        if (result.type !== 'location') {
                            setStatus('请扫描库位条码或库位二维码。', true);
                            return;
                        }
                        var select = kind === 'from' ? fromLocation : toLocation;
                        if (selectLocation(select, result.data.id, result.data.code)) {
                            setStatus((kind === 'from' ? '源' : '目标') + '库位：' + result.data.code + '。');
                        }
                        return;
                    }

                    if (kind === 'lot') {
                        if (result.type === 'lot') {
                            lotInput.value = result.data.lot_no;
                            setStatus('批次号：' + result.data.lot_no + '。');
                        } else {
                            lotInput.value = code;
                            setStatus('批次号已录入。');
                        }
                        return;
                    }

                    if (kind === 'serial') {
                        var serial = result.type === 'serial' ? result.data.serial_no : code;
                        serialInput.value = serial;
                        setStatus('序列号：' + serial + '。');
                    }
                }).catch(function (error) {
                    setStatus(error.message || '扫码失败', true);
                });
            }

            [
                ['putaway-product-scan', 'product'],
                ['putaway-from-scan', 'from'],
                ['putaway-to-scan', 'to'],
                ['lot_no', 'lot'],
                ['serial_no', 'serial']
            ].forEach(function (pair) {
                var input = document.getElementById(pair[0]);
                if (input) {
                    window.ErpScan.bind(input, { onScan: function (code) { handleScan(pair[1], code); } });
                }
            });

            document.querySelectorAll('[data-camera-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var target = button.dataset.cameraTarget;
                    var kind = target === 'putaway-product-scan' ? 'product'
                        : target === 'putaway-from-scan' ? 'from'
                        : target === 'putaway-to-scan' ? 'to'
                        : target === 'lot_no' ? 'lot' : 'serial';

                    window.ErpScan.scanCamera({
                        title: '扫描' + ({ product: '商品', from: '源库位', to: '目标库位', lot: '批次号', serial: '序列号' })[kind],
                        onResult: function (value) { handleScan(kind, value); }
                    });
                });
            });

            warehouse.addEventListener('change', filterLocations);
            product.addEventListener('change', syncTraceability);
            filterLocations();
            syncTraceability();
        })();
    </script>
@endpush
