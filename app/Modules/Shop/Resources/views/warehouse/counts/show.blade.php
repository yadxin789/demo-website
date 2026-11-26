@extends('shop::layouts.app')

@section('title', "盘点单 {$count->code}")
@section('heading', "盘点单 {$count->code}")

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'counting' => '盘点中', 'adjusted' => '已过账', 'cancelled' => '已取消'];
        $badges = ['draft' => 'text-bg-secondary', 'counting' => 'text-bg-primary', 'adjusted' => 'text-bg-success', 'cancelled' => 'text-bg-danger'];
        $editable = $count->isEditable();
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="text-muted mb-1">
                {{ $count->warehouse?->name }} · {{ $count->location?->code ?? '全部库位' }} ·
                {{ optional($count->count_date)->format('Y-m-d') }} · 操作人 {{ $count->operator?->name ?? '—' }}
            </p>
            <span class="badge {{ $badges[$count->status] ?? 'text-bg-secondary' }}">{{ $statusLabels[$count->status] ?? $count->status }}</span>
            @if ($pending > 0)
                <span class="badge text-bg-warning ms-1">尚有 {{ $pending }} 行未录入实盘</span>
            @endif
        </div>
        <a href="{{ route('shop.counts.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>返回列表</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    @if ($editable)
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-6">
                        <label for="count-scan-input" class="form-label small mb-1">扫码实盘（商品 / 批次 / 序列号）</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                            <input type="text" id="count-scan-input" class="form-control" autocomplete="off"
                                   placeholder="扫描商品条码、批次码或序列号，重复扫描自动累加">
                            <button type="button" id="count-scan-camera" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-camera"></i><span class="visually-hidden">摄像头扫码</span>
                            </button>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div id="count-scan-status" class="small text-muted" role="status" aria-live="polite">
                            等待扫码… 未录入行：<span data-pending>{{ $pending }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form method="post" action="{{ route('shop.counts.enter', $count) }}" aria-label="录入实盘数表单">
        @csrf
        <div class="card p-0">
            <div class="table-wrapper">
                <table class="table table-hover mb-0">
                    <caption id="count-line-caption" class="visually-hidden">盘点明细行</caption>
                    <thead>
                        <tr>
                            <th scope="col">商品</th>
                            <th scope="col">库位</th>
                            <th scope="col">批次</th>
                            <th scope="col">序列号</th>
                            <th scope="col" class="text-end">账面数</th>
                            <th scope="col" class="text-end">实盘数</th>
                            <th scope="col" class="text-end">盈亏数量</th>
                            <th scope="col" class="text-end">单位成本</th>
                            <th scope="col" class="text-end">盈亏金额</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($count->lines as $line)
                            <tr data-count-row="{{ $line->id }}"
                                data-book="{{ (float) $line->book_qty }}"
                                data-cost="{{ (float) $line->unit_cost }}"
                                data-product-id="{{ $line->product_id }}"
                                data-lot="{{ $line->lot_no }}"
                                data-serial="{{ $line->serial_no }}"
                                data-location="{{ $line->location?->code }}">
                                <td>
                                    {{ $line->product?->code ?? '—' }}
                                    <span class="text-muted small d-block">{{ $line->product?->name ?? '商品已删除' }}</span>
                                </td>
                                <td>{{ $line->location?->code ?? '—' }}</td>
                                <td>{{ $line->lot_no ?? '—' }}</td>
                                <td>{{ $line->serial_no ?? '—' }}</td>
                                <td class="text-end">{{ number_format((float) $line->book_qty, 4) }}</td>
                                <td class="text-end">
                                    @if ($editable)
                                        <input type="number" step="0.0001" min="0"
                                               name="counted[{{ $line->id }}]"
                                               value="{{ old('counted.' . $line->id, $line->counted_qty !== null ? (float) $line->counted_qty : '') }}"
                                               data-counted-input
                                               class="form-control form-control-sm text-end" style="inline-size:7rem;">
                                    @else
                                        {{ $line->counted_qty !== null ? number_format((float) $line->counted_qty, 4) : '—' }}
                                    @endif
                                </td>
                                <td class="text-end" data-variance-qty>{{ number_format((float) $line->variance_qty, 4) }}</td>
                                <td class="text-end">{{ number_format((float) $line->unit_cost, 2) }}</td>
                                <td class="text-end {{ (float) $line->variance_value < 0 ? 'text-danger' : ((float) $line->variance_value > 0 ? 'text-success' : '') }}"
                                    data-variance-value>
                                    {{ number_format((float) $line->variance_value, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">该范围暂无库存</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="8" class="text-end">盈亏金额合计</th>
                            <th scope="col" class="text-end {{ $totalVariance < 0 ? 'text-danger' : ($totalVariance > 0 ? 'text-success' : '') }}"
                                data-total-variance>
                                {{ number_format($totalVariance, 2) }}
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if ($editable && $count->lines->isNotEmpty())
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>保存实盘数</button>
            </div>
        @endif
    </form>

    @if ($editable && $pending === 0 && $count->lines->isNotEmpty())
        <form method="post" action="{{ route('shop.counts.post', $count) }}" class="mt-2"
              onsubmit="return confirm('确认过账？过账后将按实盘调整库存，且不能重复过账。')">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-check-double me-1"></i>过账调整
            </button>
        </form>
    @endif
@endsection

@push('scripts')
    @if ($editable)
        <script>
            (function () {
                'use strict';

                var scanUrl = @json(route('shop.counts.scan', $count));
                var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var scanInput = document.getElementById('count-scan-input');
                var statusEl = document.getElementById('count-scan-status');
                var tbody = document.querySelector('tbody');

                function setStatus(message, error) {
                    statusEl.textContent = message;
                    statusEl.className = 'small ' + (error ? 'text-danger' : 'text-muted');
                }

                function pendingCount() {
                    var count = 0;
                    document.querySelectorAll('[data-count-row] [data-counted-input]').forEach(function (input) {
                        if (input.value === '') { count++; }
                    });
                    return count;
                }

                function money(value) {
                    return (Number(value) || 0).toFixed(2);
                }

                function updateRow(row, counted) {
                    var book = Number(row.dataset.book || 0);
                    var cost = Number(row.dataset.cost || 0);
                    var variance = counted - book;
                    var value = variance * cost;

                    row.querySelector('[data-variance-qty]').textContent = variance.toFixed(4);
                    var valueCell = row.querySelector('[data-variance-value]');
                    valueCell.textContent = money(value);
                    valueCell.classList.remove('text-success', 'text-danger');
                    if (value > 0) { valueCell.classList.add('text-success'); }
                    if (value < 0) { valueCell.classList.add('text-danger'); }
                }

                function updateTotal() {
                    var total = 0;
                    document.querySelectorAll('[data-count-row]').forEach(function (row) {
                        var input = row.querySelector('[data-counted-input]');
                        if (!input || input.value === '') { return; }
                        total += (Number(input.value) - Number(row.dataset.book || 0)) * Number(row.dataset.cost || 0);
                    });

                    var cell = document.querySelector('[data-total-variance]');
                    cell.textContent = money(total);
                    cell.classList.remove('text-success', 'text-danger');
                    if (total > 0) { cell.classList.add('text-success'); }
                    if (total < 0) { cell.classList.add('text-danger'); }
                }

                function refreshPending() {
                    var target = document.querySelector('[data-pending]');
                    if (target) { target.textContent = String(pendingCount()); }
                }

                function highlight(row) {
                    document.querySelectorAll('[data-count-row]').forEach(function (item) {
                        item.classList.remove('table-primary');
                    });
                    row.classList.add('table-primary');
                    row.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }

                function createRow(line) {
                    var row = document.createElement('tr');
                    row.dataset.countRow = line.line_id;
                    row.dataset.book = line.book_qty;
                    row.dataset.cost = line.unit_cost || 0;
                    row.dataset.productId = line.product_id;
                    row.dataset.lot = line.lot_no || '';
                    row.dataset.serial = line.serial_no || '';

                    var productCell = document.createElement('td');
                    productCell.textContent = line.product_code || '—';
                    var name = document.createElement('span');
                    name.className = 'text-muted small d-block';
                    name.textContent = line.product_name || '';
                    productCell.appendChild(name);

                    var locationCell = document.createElement('td');
                    locationCell.textContent = line.location_code || '—';
                    var lotCell = document.createElement('td');
                    lotCell.textContent = line.lot_no || '—';
                    var serialCell = document.createElement('td');
                    serialCell.textContent = line.serial_no || '—';

                    var bookCell = document.createElement('td');
                    bookCell.className = 'text-end';
                    bookCell.textContent = Number(line.book_qty || 0).toFixed(4);

                    var countedCell = document.createElement('td');
                    countedCell.className = 'text-end';
                    var input = document.createElement('input');
                    input.type = 'number';
                    input.step = '0.0001';
                    input.min = '0';
                    input.name = 'counted[' + line.line_id + ']';
                    input.className = 'form-control form-control-sm text-end';
                    input.style.inlineSize = '7rem';
                    input.dataset.countedInput = '';
                    countedCell.appendChild(input);

                    var varianceCell = document.createElement('td');
                    varianceCell.className = 'text-end';
                    varianceCell.dataset.varianceQty = '';
                    var costCell = document.createElement('td');
                    costCell.className = 'text-end';
                    costCell.textContent = money(line.unit_cost);
                    var valueCell = document.createElement('td');
                    valueCell.className = 'text-end';
                    valueCell.dataset.varianceValue = '';

                    [productCell, locationCell, lotCell, serialCell, bookCell, countedCell, varianceCell, costCell, valueCell]
                        .forEach(function (cell) { row.appendChild(cell); });

                    tbody.appendChild(row);
                    return row;
                }

                function applyLine(line) {
                    var row = document.querySelector('[data-count-row="' + line.line_id + '"]');
                    var created = false;
                    if (!row) {
                        row = createRow(line);
                        created = true;
                    }

                    var input = row.querySelector('[data-counted-input]');
                    input.value = String(line.counted_qty);
                    updateRow(row, Number(line.counted_qty));
                    updateTotal();
                    refreshPending();
                    highlight(row);

                    setStatus((created ? '新增盘盈行：' : '已累加：') + line.product_code
                        + (line.lot_no ? ' / 批次 ' + line.lot_no : '')
                        + (line.serial_no ? ' / 序列号 ' + line.serial_no : '')
                        + '，实盘 ' + Number(line.counted_qty).toFixed(4) + '。');
                }

                function scan(code) {
                    setStatus('正在识别 ' + code + ' …');
                    fetch(scanUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ code: code }),
                    }).then(function (response) {
                        return response.json().then(function (data) { return { ok: response.ok, data: data }; });
                    }).then(function (result) {
                        if (!result.ok || !result.data.ok) {
                            throw new Error((result.data && result.data.message) || '扫码失败');
                        }
                        applyLine(result.data.line);
                    }).catch(function (error) {
                        setStatus(error.message || '扫码失败', true);
                    });
                }

                if (scanInput && window.ErpScan) {
                    window.ErpScan.bind(scanInput, { onScan: scan });
                }

                var camera = document.getElementById('count-scan-camera');
                if (camera) {
                    camera.addEventListener('click', function () {
                        window.ErpScan.scanCamera({ title: '扫描盘点商品', onResult: scan });
                    });
                }

                document.querySelectorAll('[data-counted-input]').forEach(function (input) {
                    input.addEventListener('input', function () {
                        var row = input.closest('[data-count-row]');
                        updateRow(row, input.value === '' ? 0 : Number(input.value));
                        updateTotal();
                        refreshPending();
                    });
                });
            })();
        </script>
    @endif
@endpush
