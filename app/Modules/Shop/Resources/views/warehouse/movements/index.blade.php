@extends('shop::layouts.app')

@section('title', '库存流水')
@section('heading', '库存流水')

@section('content')
    @php
        $sourceLabels = [
            'inbound' => '入库',
            'sales_order' => '销售出库',
            'transfer' => '调拨',
            'adjust' => '盘点调整',
            'return' => '退货',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $movements->total() }} 条流水（只读，不可编辑或删除）</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="{{ route('shop.movements.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="filter-product" class="form-label">商品编码 / 名称 / 条码</label>
                    <input type="text" id="filter-product" name="product" value="{{ request('product') }}" class="form-control" placeholder="模糊匹配">
                </div>
                <div class="col-12 col-md-3">
                    <label for="filter-location" class="form-label">库位</label>
                    <select id="filter-location" name="location_id" class="form-select">
                        <option value="">全部库位</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>
                                {{ $location->code }} · {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="filter-direction" class="form-label">方向</label>
                    <select id="filter-direction" name="direction" class="form-select">
                        <option value="">全部</option>
                        @foreach ($directions as $value => $label)
                            <option value="{{ $value }}" @selected(request('direction') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="filter-source" class="form-label">单据号</label>
                    <input type="text" id="filter-source" name="source_code" value="{{ request('source_code') }}" class="form-control" placeholder="单据编号">
                </div>
                <div class="col-6 col-md-2">
                    <label for="filter-from" class="form-label">日期起</label>
                    <input type="date" id="filter-from" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label for="filter-to" class="form-label">日期止</label>
                    <input type="date" id="filter-to" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>筛选</button>
                    <a href="{{ route('shop.movements.index') }}" class="btn btn-outline-secondary">重置</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="movement-table-caption" class="visually-hidden">库存流水列表</caption>
                <thead>
                    <tr>
                        <th scope="col">日期</th>
                        <th scope="col">商品</th>
                        <th scope="col">库位</th>
                        <th scope="col">批次</th>
                        <th scope="col">序列号</th>
                        <th scope="col">方向</th>
                        <th scope="col" class="text-end">变动前</th>
                        <th scope="col" class="text-end">变动后</th>
                        <th scope="col" class="text-end">变动量</th>
                        <th scope="col" class="text-end">单位成本</th>
                        <th scope="col">单据号</th>
                        <th scope="col">来源</th>
                        <th scope="col">操作人</th>
                        <th scope="col">备注</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td>{{ optional($movement->movement_date)->format('Y-m-d H:i') }}</td>
                            <td>
                                {{ $movement->product?->code ?? '—' }}
                                <span class="text-muted small d-block">{{ $movement->product?->name ?? '商品已删除' }}</span>
                            </td>
                            <td>{{ $movement->location?->code ?? '—' }}</td>
                            <td>{{ $movement->lot_no ?? '—' }}</td>
                            <td>{{ $movement->serial_no ?? '—' }}</td>
                            <td>
                                @php
                                    $badge = [
                                        'in' => 'text-bg-success',
                                        'out' => 'text-bg-danger',
                                        'transfer' => 'text-bg-primary',
                                        'adjust' => 'text-bg-warning',
                                    ][$movement->direction] ?? 'text-bg-secondary';
                                @endphp
                                <span class="badge {{ $badge }}">{{ $directions[$movement->direction] ?? $movement->direction }}</span>
                            </td>
                            <td class="text-end">{{ number_format((float) $movement->qty_before, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $movement->qty_after, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $movement->qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $movement->unit_cost, 2) }}</td>
                            <td>{{ $movement->source_code ?? '—' }}</td>
                            <td>{{ $sourceLabels[$movement->source_type] ?? $movement->source_type ?? '—' }}</td>
                            <td>{{ $movement->operator?->name ?? '—' }}</td>
                            <td>{{ $movement->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="text-center text-muted py-4">暂无库存流水</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $movements->links() }}
    </div>
@endsection
