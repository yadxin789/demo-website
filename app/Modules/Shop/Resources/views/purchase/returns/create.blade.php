@extends('shop::layouts.app')

@section('title', "采购退货 · 收货单 {$receipt->code}")
@section('heading', "采购退货 · 收货单 {$receipt->code}")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            供应商：{{ $receipt->supplier?->name ?? '—' }} · 仓库：{{ $receipt->warehouse?->name ?? '—' }}
        </p>
        <a href="{{ route('shop.purchase-returns.create') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>重选收货单
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <form method="post" action="{{ route('shop.purchase-returns.store') }}" aria-label="采购退货录入表单">
        @csrf
        <input type="hidden" name="receipt_id" value="{{ $receipt->id }}">

        <div class="card p-0 mb-3">
            <div class="table-wrapper">
                <table class="table table-hover mb-0">
                    <caption class="visually-hidden">退货明细行</caption>
                    <thead>
                        <tr>
                            <th scope="col">商品</th>
                            <th scope="col">批次</th>
                            <th scope="col">序列号</th>
                            <th scope="col" class="text-end">已收</th>
                            <th scope="col" class="text-end">已退</th>
                            <th scope="col" class="text-end">本次退货</th>
                            <th scope="col">退货原因</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receipt->lines as $index => $line)
                            @php
                                $received = (float) $line->qty;
                                $returned = (float) ($returned[$line->id] ?? 0);
                                $remaining = $received - $returned;
                            @endphp
                            @if ($remaining > 0)
                                <tr>
                                    <td>
                                        {{ $line->product?->code ?? '—' }}
                                        <span class="text-muted small d-block">{{ $line->product?->name ?? '商品已删除' }}</span>
                                    </td>
                                    <td>{{ $line->lot_no ?? '—' }}</td>
                                    <td>{{ $line->serial_no ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($received, 4) }}</td>
                                    <td class="text-end">{{ number_format($returned, 4) }}</td>
                                    <td class="text-end">
                                        <input type="hidden" name="lines[{{ $index }}][receipt_line_id]" value="{{ $line->id }}">
                                        <input type="hidden" name="lines[{{ $index }}][product_id]" value="{{ $line->product_id }}">
                                        <input type="hidden" name="lines[{{ $index }}][lot_no]" value="{{ $line->lot_no }}">
                                        <input type="hidden" name="lines[{{ $index }}][serial_no]" value="{{ $line->serial_no }}">
                                        <input type="number" step="0.0001" min="0.0001" max="{{ $remaining }}"
                                               name="lines[{{ $index }}][qty]" value="{{ old("lines.$index.qty") }}"
                                               class="form-control form-control-sm text-end" style="inline-size:6rem;" required>
                                    </td>
                                    <td>
                                        <input type="text" maxlength="255" name="lines[{{ $index }}][reason]"
                                               value="{{ old("lines.$index.reason") }}" class="form-control form-control-sm">
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label for="return_date" class="form-label">退货日期</label>
                <input type="date" id="return_date" name="return_date"
                       value="{{ old('return_date', date('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-md-8">
                <label for="reason" class="form-label">退货原因（整单）</label>
                <input type="text" id="reason" name="reason" maxlength="255"
                       value="{{ old('reason') }}" class="form-control">
            </div>
        </div>

        <div class="mb-3">
            <label for="note" class="form-label">备注</label>
            <textarea id="note" name="note" rows="2" maxlength="255" class="form-control">{{ old('note') }}</textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存退货草稿</button>
            <a href="{{ route('shop.purchase-returns.index') }}" class="btn btn-outline-secondary">取消</a>
        </div>
    </form>
@endsection
