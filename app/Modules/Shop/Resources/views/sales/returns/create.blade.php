@extends('shop::layouts.app')

@section('title', "销售退货 · {$order->code}")
@section('heading', "销售退货 · {$order->code}")

@section('content')
    @php $isSales = $source === 'sales_order'; @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            来源：{{ $isSales ? '销售订单' : 'POS 订单' }} · 客户：{{ $order->customer?->name ?? '散客' }}
        </p>
        <a href="{{ route('shop.sales-returns.create') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>重选原单
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <form method="post" action="{{ route('shop.sales-returns.store') }}" aria-label="销售退货录入表单">
        @csrf
        <input type="hidden" name="source_type" value="{{ $source }}">
        @if ($isSales)
            <input type="hidden" name="sales_order_id" value="{{ $order->id }}">
        @else
            <input type="hidden" name="pos_order_id" value="{{ $order->id }}">
        @endif

        <div class="card p-0 mb-3">
            <div class="table-wrapper">
                <table class="table table-hover mb-0">
                    <caption class="visually-hidden">退货明细行</caption>
                    <thead>
                        <tr>
                            <th scope="col">商品</th>
                            <th scope="col">批次</th>
                            <th scope="col" class="text-end">原售</th>
                            <th scope="col" class="text-end">已退</th>
                            <th scope="col" class="text-end">本次退货</th>
                            <th scope="col">退货原因</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->lines as $index => $line)
                            @php
                                $original = (float) $line->qty;
                                $returned = (float) ($returned[$line->id] ?? 0);
                                $remaining = $original - $returned;
                            @endphp
                            @if ($remaining > 0)
                                <tr>
                                    <td>
                                        {{ $line->product?->code ?? '—' }}
                                        <span class="text-muted small d-block">{{ $line->product?->name ?? '商品已删除' }}</span>
                                    </td>
                                    <td>{{ $line->lot_no ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($original, 4) }}</td>
                                    <td class="text-end">{{ number_format($returned, 4) }}</td>
                                    <td class="text-end">
                                        @if ($isSales)
                                            <input type="hidden" name="lines[{{ $index }}][sales_order_line_id]" value="{{ $line->id }}">
                                        @else
                                            <input type="hidden" name="lines[{{ $index }}][pos_order_line_id]" value="{{ $line->id }}">
                                        @endif
                                        <input type="hidden" name="lines[{{ $index }}][product_id]" value="{{ $line->product_id }}">
                                        <input type="hidden" name="lines[{{ $index }}][lot_no]" value="{{ $line->lot_no }}">
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
            <div class="col-md-4">
                <label for="refund_method" class="form-label">退款方式</label>
                <select id="refund_method" name="refund_method" class="form-select">
                    <option value="credit" @selected(old('refund_method', 'credit') === 'credit')>冲减应收</option>
                    <option value="cash" @selected(old('refund_method') === 'cash')>退现金</option>
                    <option value="bank" @selected(old('refund_method') === 'bank')>退银行</option>
                </select>
            </div>
            <div class="col-md-4">
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
            <a href="{{ route('shop.sales-returns.index') }}" class="btn btn-outline-secondary">取消</a>
        </div>
    </form>
@endsection
