@extends('shop::layouts.app')

@section('title', "收货单 · {$purchaseOrder->code}")
@section('heading', "收货单 · 采购单 {$purchaseOrder->code}")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            供应商：{{ $purchaseOrder->supplier?->name ?? '—' }} · 仓库：{{ $purchaseOrder->warehouse?->name ?? '—' }}
        </p>
        <a href="{{ route('shop.purchase-orders.show', $purchaseOrder) }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>返回采购单
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-6">
                    <label for="receipt-scan-input" class="form-label small mb-1">扫码录入（商品 / 库位 / 批次 / 序列号）</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                        <input type="text" id="receipt-scan-input" class="form-control" autocomplete="off"
                               autofocus placeholder="扫描商品条码 / 二维码，或扫描序列号">
                        <button type="button" id="receipt-scan-camera" class="btn btn-outline-secondary" title="摄像头扫码">
                            <i class="fa-solid fa-camera"></i><span class="visually-hidden">摄像头扫码</span>
                        </button>
                    </div>
                    <div class="form-text">
                        扫商品码定位采购行并累加；扫序列号自动追加到该商品行；扫库位码自动选中库位。
                    </div>
                </div>
                <div class="col-lg-6">
                    <div id="receipt-scan-status" class="small text-muted" role="status" aria-live="polite">
                        等待扫码…
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="{{ route('shop.receipts.store') }}" aria-label="收货录入表单">
        @csrf
        <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
        <input type="hidden" name="warehouse_id" value="{{ $purchaseOrder->warehouse_id }}">

        <div class="card p-0 mb-3">
            <div class="table-wrapper">
                <table class="table table-hover mb-0 align-middle">
                    <caption class="visually-hidden">收货明细行</caption>
                    <thead>
                        <tr>
                            <th scope="col">商品</th>
                            <th scope="col" class="text-end">订单量</th>
                            <th scope="col" class="text-end">已收</th>
                            <th scope="col" class="text-end">本次收货</th>
                            <th scope="col">库位</th>
                            <th scope="col">批次号</th>
                            <th scope="col">序列号（逐号扫描）</th>
                            <th scope="col" class="text-end">单价</th>
                            <th scope="col">备注</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchaseOrder->lines as $index => $line)
                            @php
                                $received = (float) ($received[$line->id] ?? 0);
                                $remaining = (float) $line->qty - $received;
                                $traceability = $line->product?->traceability ?? 'none';
                                $isSerial = $traceability === 'serial';
                                $oldSerials = old("lines.$index.serials", '');
                                if (is_array($oldSerials)) {
                                    $oldSerials = implode("\n", $oldSerials);
                                }
                            @endphp
                            @if ($remaining > 0)
                                <tr data-receipt-row
                                    data-product-id="{{ $line->product_id }}"
                                    data-product-code="{{ $line->product?->code }}"
                                    data-traceability="{{ $traceability }}"
                                    data-remaining="{{ $remaining }}">
                                    <td>
                                        {{ $line->product?->code ?? '—' }}
                                        <span class="text-muted small d-block">{{ $line->product?->name ?? '商品已删除' }}</span>
                                        @if ($isSerial)
                                            <span class="badge text-bg-info mt-1">序列号追踪</span>
                                        @elseif ($traceability === 'lot')
                                            <span class="badge text-bg-info mt-1">批次追踪</span>
                                        @endif
                                        <span class="badge text-bg-success mt-1 d-none" data-row-active>当前扫码行</span>
                                    </td>
                                    <td class="text-end">{{ number_format((float) $line->qty, 4) }}</td>
                                    <td class="text-end">{{ number_format($received, 4) }}</td>
                                    <td class="text-end">
                                        <input type="hidden" name="lines[{{ $index }}][purchase_order_line_id]" value="{{ $line->id }}">
                                        <input type="hidden" name="lines[{{ $index }}][product_id]" value="{{ $line->product_id }}">
                                        <input type="number" step="0.0001" min="0.0001" max="{{ $remaining }}"
                                               name="lines[{{ $index }}][qty]"
                                               value="{{ old("lines.$index.qty", $isSerial ? '' : $remaining) }}"
                                               data-receipt-qty
                                               class="form-control form-control-sm text-end" style="inline-size:6rem;" required>
                                    </td>
                                    <td>
                                        <select name="lines[{{ $index }}][location_id]" class="form-select form-select-sm" data-location-select>
                                            <option value="">默认库位</option>
                                            @foreach ($locations as $location)
                                                <option value="{{ $location->id }}" @selected((int) old("lines.$index.location_id", 0) === $location->id)>
                                                    {{ $location->code }} · {{ $location->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" maxlength="50" name="lines[{{ $index }}][lot_no]"
                                               value="{{ old("lines.$index.lot_no") }}" class="form-control form-control-sm"
                                               data-lot-input @if ($traceability === 'lot') placeholder="扫描批次码或输入" @endif>
                                    </td>
                                    <td>
                                        @if ($isSerial)
                                            <textarea name="lines[{{ $index }}][serials]" rows="3" data-serial-input
                                                      class="form-control form-control-sm" style="min-inline-size:10rem;"
                                                      placeholder="每行一个序列号，可连续扫描">{{ $oldSerials }}</textarea>
                                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" data-serial-camera>
                                                <i class="fa-solid fa-camera me-1"></i>扫描序列号
                                            </button>
                                            <div class="form-text">数量将自动等于序列号个数。</div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <input type="number" step="0.0001" min="0" name="lines[{{ $index }}][unit_cost]"
                                               value="{{ old("lines.$index.unit_cost", (float) $line->unit_price) }}"
                                               class="form-control form-control-sm text-end" style="inline-size:6rem;">
                                    </td>
                                    <td>
                                        <input type="text" maxlength="255" name="lines[{{ $index }}][line_note]" value="{{ old("lines.$index.line_note") }}" class="form-control form-control-sm">
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-3">
            <label for="note" class="form-label">收货备注</label>
            <textarea id="note" name="note" rows="2" maxlength="255" class="form-control">{{ old('note') }}</textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>确认入库</button>
            <a href="{{ route('shop.purchase-orders.show', $purchaseOrder) }}" class="btn btn-outline-secondary">取消</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            'use strict';

            var warehouseId = {{ (int) $purchaseOrder->warehouse_id }};
            var scanInput = document.getElementById('receipt-scan-input');
            var statusEl = document.getElementById('receipt-scan-status');
            var rows = Array.prototype.slice.call(document.querySelectorAll('[data-receipt-row]'));
            var activeRow = null;

            function setStatus(message, isError) {
                if (!statusEl) { return; }
                statusEl.textContent = message;
                statusEl.className = 'small ' + (isError ? 'text-danger' : 'text-muted');
            }

            function findByProduct(productId) {
                return rows.find(function (row) {
                    return Number(row.dataset.productId) === Number(productId);
                }) || null;
            }

            function serialsOf(row) {
                var input = row.querySelector('[data-serial-input]');
                if (!input) { return []; }
                return input.value.split(/[\s,，、]+/).map(function (v) { return v.trim(); }).filter(Boolean);
            }

            function syncQty(row) {
                if (!row || row.dataset.traceability !== 'serial') { return; }
                var qtyInput = row.querySelector('[data-receipt-qty]');
                var count = serialsOf(row).length;
                if (qtyInput) { qtyInput.value = count > 0 ? String(count) : ''; }
                setStatus('当前行已录入 ' + count + ' 个序列号。');
            }

            function activate(row) {
                if (!row) { return; }
                if (activeRow && activeRow !== row) {
                    activeRow.classList.remove('table-primary');
                    var oldBadge = activeRow.querySelector('[data-row-active]');
                    if (oldBadge) { oldBadge.classList.add('d-none'); }
                }
                activeRow = row;
                row.classList.add('table-primary');
                var badge = row.querySelector('[data-row-active]');
                if (badge) { badge.classList.remove('d-none'); }
                row.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }

            function appendSerial(row, serial) {
                var input = row.querySelector('[data-serial-input]');
                if (!input) { return; }

                var serials = serialsOf(row);
                if (serials.indexOf(serial) !== -1) {
                    setStatus('序列号 ' + serial + ' 已在本行，请勿重复扫描。', true);
                    return;
                }
                if (serials.length >= Number(row.dataset.remaining)) {
                    setStatus('本行序列号已达到可收数量 ' + row.dataset.remaining + '。', true);
                    return;
                }

                input.value = (input.value ? input.value.replace(/\s+$/, '') + '\n' : '') + serial;
                syncQty(row);
                activate(row);
                input.focus();
                input.scrollTop = input.scrollHeight;
            }

            function handleResult(result) {
                if (!result || !result.type) { return; }

                if (result.type === 'product') {
                    var row = findByProduct(result.data.id);
                    if (!row) {
                        setStatus('商品 ' + result.data.code + ' 不在本采购单待收明细中。', true);
                        return;
                    }
                    activate(row);

                    if (row.dataset.traceability === 'serial') {
                        var serialInput = row.querySelector('[data-serial-input]');
                        if (serialInput) { serialInput.focus(); }
                        setStatus('已定位 ' + result.data.code + '，请逐号扫描序列号。');
                        return;
                    }

                    var qtyInput = row.querySelector('[data-receipt-qty]');
                    var remaining = Number(row.dataset.remaining);
                    var current = Number(qtyInput.value || 0);
                    if (!row.dataset.scanStarted) {
                        qtyInput.value = '1';
                        row.dataset.scanStarted = '1';
                    } else {
                        qtyInput.value = String(Math.min(remaining, current + 1));
                    }
                    setStatus('已定位 ' + result.data.code + '，本次收货 ' + qtyInput.value + '。');
                    qtyInput.focus();
                    qtyInput.select();
                    return;
                }

                if (result.type === 'serial') {
                    var serialRow = findByProduct(result.data.product_id);
                    if (!serialRow) {
                        setStatus('序列号 ' + result.data.serial_no + ' 对应商品不在本采购单。', true);
                        return;
                    }
                    appendSerial(serialRow, result.data.serial_no);
                    return;
                }

                if (result.type === 'location') {
                    if (!activeRow) {
                        setStatus('请先扫描商品定位收货行，再扫描库位。', true);
                        return;
                    }
                    var select = activeRow.querySelector('[data-location-select]');
                    var option = select ? select.querySelector('option[value="' + result.data.id + '"]') : null;
                    if (option) {
                        select.value = result.data.id;
                        setStatus('已选择库位 ' + result.data.code + '。');
                    } else {
                        setStatus('库位 ' + result.data.code + ' 不属于当前仓库。', true);
                    }
                    return;
                }

                if (result.type === 'lot') {
                    var lotRow = activeRow || findByProduct(result.data.product_id);
                    if (!lotRow) {
                        setStatus('请先扫描商品定位收货行，再扫描批次。', true);
                        return;
                    }
                    activate(lotRow);
                    var lotInput = lotRow.querySelector('[data-lot-input]');
                    if (lotInput) { lotInput.value = result.data.lot_no; }
                    setStatus('已录入批次 ' + result.data.lot_no + '。');
                }
            }

            function resolveAndHandle(code) {
                window.ErpScan.resolve(code, warehouseId)
                    .then(handleResult)
                    .catch(function (error) {
                        setStatus(error.message || '扫码失败', true);
                    });
            }

            if (scanInput && window.ErpScan) {
                window.ErpScan.bind(scanInput, { onScan: resolveAndHandle });
            }

            document.getElementById('receipt-scan-camera')?.addEventListener('click', function () {
                if (!window.ErpScan) { return; }
                window.ErpScan.scanCamera({
                    title: '扫描商品 / 库位 / 序列号',
                    onResult: resolveAndHandle
                });
            });

            rows.forEach(function (row) {
                var serialInput = row.querySelector('[data-serial-input]');
                if (serialInput) {
                    serialInput.addEventListener('input', function () { syncQty(row); });
                }

                var cameraButton = row.querySelector('[data-serial-camera]');
                if (cameraButton) {
                    cameraButton.addEventListener('click', function () {
                        activate(row);
                        if (!window.ErpScan) { return; }
                        window.ErpScan.scanCamera({
                            title: '扫描序列号',
                            onResult: function (value) { appendSerial(row, value); }
                        });
                    });
                }

                var qtyInput = row.querySelector('[data-receipt-qty]');
                if (qtyInput) {
                    qtyInput.addEventListener('input', function () {
                        if (row.dataset.traceability !== 'serial') { row.dataset.scanStarted = '1'; }
                    });
                }
            });
        })();
    </script>
@endpush
