@extends('shop::layouts.app')

@section('title', '库位标签')
@section('heading', '库位标签')

@push('styles')
    <style>
        .location-label-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
        .location-label { border: 1px dashed #adb5bd; border-radius: .35rem; padding: .6rem; break-inside: avoid; background: #fff; }
        .location-label .label-code { font-size: 1.1rem; font-weight: 700; letter-spacing: .05em; }
        .location-label .label-meta { font-size: .75rem; color: #6c757d; }
        .location-label .label-codes { display: flex; align-items: center; gap: .5rem; margin-top: .4rem; }
        .location-label .label-barcode { flex: 1 1 auto; min-inline-size: 0; }
        .location-label .label-qr { inline-size: 84px; block-size: 84px; flex: 0 0 auto; }
        @media print {
            .app-sidebar, .app-topbar, .no-print, nav[aria-label="breadcrumb"] { display: none !important; }
            .app-main { margin: 0 !important; padding: 0 !important; }
            .location-label-grid { grid-template-columns: repeat(3, 1fr); gap: .4rem; }
            .location-label { border-color: #000; }
            .card { border: 0 !important; box-shadow: none !important; }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <p class="text-muted mb-0">勾选库位后生成 CODE128 条码与二维码标签，可直接打印粘贴。</p>
        <div class="d-flex gap-2">
            <a href="{{ route('shop.locations.index') }}" class="btn btn-outline-secondary">返回库位列表</a>
            @if ($labelLocations->isNotEmpty())
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i>打印标签
                </button>
            @endif
        </div>
    </div>

    <div class="card mb-3 no-print">
        <div class="card-body">
            <form method="get" action="{{ route('shop.locations.labels.index') }}">
                <div class="row g-2">
                    @foreach ($locations as $location)
                        <div class="col-md-4 col-xl-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="selected[]"
                                       value="{{ $location->id }}" id="location-{{ $location->id }}"
                                       @checked(in_array($location->id, $selected, false))>
                                <label class="form-check-label" for="location-{{ $location->id }}">
                                    {{ $location->code }} · {{ $location->name }}
                                    <span class="text-muted small d-block">{{ $location->warehouse?->name }} · {{ $location->barcode ?: '无条码' }}</span>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">生成标签</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="select-all-locations">全选</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-locations">清空</button>
                </div>
            </form>
        </div>
    </div>

    @if ($labelLocations->isEmpty())
        <div class="alert alert-info no-print" role="alert">请选择需要打印标签的库位。</div>
    @else
        <div class="location-label-grid">
            @foreach ($labelLocations as $location)
                @php($payload = \App\Modules\Shop\Support\ScanCode::location($location))
                <div class="location-label">
                    <div class="label-code">{{ $location->code }}</div>
                    <div class="label-meta">{{ $location->name }} · {{ $location->warehouse?->name }}</div>
                    <div class="label-meta">{{ $location->barcode ?: '无条码' }}</div>
                    <div class="label-codes">
                        <canvas class="label-barcode" data-barcode="{{ $location->barcode ?: $location->code }}"></canvas>
                        <div class="label-qr" data-qr="{{ $payload }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            'use strict';

            document.querySelectorAll('[data-barcode]').forEach(function (canvas) {
                window.ErpScan.renderBarcode(canvas, canvas.dataset.barcode, { height: 40, width: 1.2, fontSize: 11 });
            });

            document.querySelectorAll('[data-qr]').forEach(function (element) {
                window.ErpScan.renderQr(element, element.dataset.qr, 84);
            });

            var selectAll = document.getElementById('select-all-locations');
            if (selectAll) {
                selectAll.addEventListener('click', function () {
                    document.querySelectorAll('input[name="selected[]"]').forEach(function (input) { input.checked = true; });
                });
            }

            var clear = document.getElementById('clear-locations');
            if (clear) {
                clear.addEventListener('click', function () {
                    document.querySelectorAll('input[name="selected[]"]').forEach(function (input) { input.checked = false; });
                });
            }
        })();
    </script>
@endpush
