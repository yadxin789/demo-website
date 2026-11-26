@extends('shop::layouts.app')

@section('title', '商品标签打印')
@section('heading', '商品标签条码打印')

@push('styles')
<style>
    .label-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1rem;
    }
    .price-label {
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        padding: .75rem;
        display: flex;
        flex-direction: column;
        gap: .35rem;
        background: #fff;
    }
    .price-label .label-name {
        font-weight: 600;
        font-size: .95rem;
        word-break: break-all;
    }
    .price-label .label-price {
        font-size: 1.15rem;
        color: #212529;
    }
    .price-label canvas { width: 100%; }
    .price-label .label-codes { display: flex; align-items: center; gap: .4rem; }
    .price-label .barcode-canvas { flex: 1 1 auto; min-inline-size: 0; }
    .price-label .label-qr { inline-size: 68px; block-size: 68px; flex: 0 0 auto; }
    @media print {
        .no-print { display: none !important; }
        .app-shell > div:first-child, .app-main, .navbar, .footer {
            margin: 0; padding: 0;
        }
        .label-grid { grid-template-columns: repeat(2, 1fr); }
        .price-label { border: none; break-inside: avoid; }
    }
</style>
@endpush

@section('content')
    <div class="no-print mb-4">
        <form method="get" action="{{ route('shop.products.labels.index') }}" class="card p-3" aria-label="标签筛选表单">
            <div class="row g-2 align-items-end">
                <div class="col-sm-5">
                    <label for="q" class="form-label">搜索（编码 / 名称 / 条码）</label>
                    <input type="text" id="q" name="q" class="form-control" value="{{ request('q') }}">
                </div>
                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass me-1"></i>筛选</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>打印标签</button>
                </div>
            </div>
        </form>

        <div class="card mt-3 p-0">
            <div class="table-wrapper">
                <table class="table table-hover mb-0">
                    <caption class="visually-hidden">可选商品列表</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width:3rem">选择</th>
                            <th scope="col">编码</th>
                            <th scope="col">名称</th>
                            <th scope="col">条码</th>
                            <th scope="col" class="text-end">零售价</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input" name="selected[]"
                                           form="label-select-form" value="{{ $product->id }}"
                                           @checked(in_array($product->id, $selected))>
                                </td>
                                <td>{{ $product->code }}</td>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->barcode ?: '—' }}</td>
                                <td class="text-end">{{ number_format((float) $product->list_price, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form id="label-select-form" method="get" action="{{ route('shop.products.labels.index') }}" class="mt-2">
            <input type="hidden" name="q" value="{{ request('q') }}">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-tags me-1"></i>生成选中商品标签</button>
        </form>
    </div>

    <h2 class="h5 mb-3">标签预览（{{ $labelProducts->count() }} 张）</h2>

    @if ($labelProducts->isEmpty())
        <p class="text-muted">请先勾选商品并生成标签预览。</p>
    @else
        <div class="label-grid">
            @foreach ($labelProducts as $product)
                @php $barcode = $product->barcode ?: $product->code; @endphp
                <div class="price-label">
                    <div class="label-name">{{ $product->name }}</div>
                    <div class="text-muted small">{{ $product->code }}</div>
                    <div class="label-price">{{ config('shop.currency_symbol') }} {{ number_format((float) $product->list_price, 2) }}</div>
                    <div class="label-codes">
                        <canvas class="barcode-canvas" data-barcode="{{ $barcode }}"></canvas>
                        <div class="label-qr" data-qr="ERP-PRODUCT:{{ $product->code }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.barcode-canvas').forEach(function (canvas) {
            var code = canvas.dataset.barcode;
            if (!code) { return; }
            window.ErpScan.renderBarcode(canvas, code, {
                width: 1,
                height: 36,
                displayValue: true,
                fontSize: 12,
                margin: 0,
                background: '#fff',
                lineColor: '#111'
            });
        });

        document.querySelectorAll('[data-qr]').forEach(function (element) {
            window.ErpScan.renderQr(element, element.dataset.qr, 68);
        });
    });
</script>
@endpush
