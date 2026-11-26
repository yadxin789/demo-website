@extends('shop::layouts.app')

@section('title', '序列号台账')
@section('heading', '序列号台账')

@section('content')
    @php
        $tabs = [
            '' => '全部',
            'on_hand' => '在库',
            'returned' => '退回',
            'sold' => '已售',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $serials->total() }} 条序列号记录（一货一码）</p>
    </div>

    <form method="get" action="{{ route('shop.serials.index') }}" class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-md-3">
            <label for="filter-serial" class="form-label">序列号</label>
            <input type="text" id="filter-serial" name="serial" value="{{ request('serial') }}" class="form-control" placeholder="序列号模糊匹配">
        </div>
        <div class="col-12 col-md-3">
            <label for="filter-product" class="form-label">商品编码 / 名称</label>
            <input type="text" id="filter-product" name="product" value="{{ request('product') }}" class="form-control" placeholder="模糊匹配">
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>筛选</button>
            <a href="{{ route('shop.serials.index') }}" class="btn btn-outline-secondary">重置</a>
        </div>
    </form>

    <div class="mb-3 d-flex gap-2 flex-wrap">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('shop.serials.index', array_filter(['status' => $key, 'serial' => request('serial'), 'product' => request('product')])) }}"
               class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="serial-table-caption" class="visually-hidden">序列号台账</caption>
                <thead>
                    <tr>
                        <th scope="col">序列号</th>
                        <th scope="col">商品</th>
                        <th scope="col">库位</th>
                        <th scope="col">批次</th>
                        <th scope="col" class="text-end">现存量</th>
                        <th scope="col">状态</th>
                        <th scope="col">最近单据</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($serials as $level)
                        @php
                            $onHand = (float) $level->qty > 0;
                            $isReturned = $onHand && $level->latestMovement?->source_type === 'sales_return';
                            $stateLabel = $isReturned ? '退回' : ($onHand ? '在库' : '已售');
                            $badge = $isReturned ? 'text-bg-warning' : ($onHand ? 'text-bg-success' : 'text-bg-secondary');
                        @endphp
                        <tr>
                            <td>{{ $level->serial_no }}</td>
                            <td>
                                {{ $level->product?->code ?? '—' }}
                                <span class="text-muted small d-block">{{ $level->product?->name ?? '商品已删除' }}</span>
                            </td>
                            <td>{{ $level->location?->code ?? '—' }}</td>
                            <td>{{ $level->lot_no ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $level->qty, 4) }}</td>
                            <td><span class="badge {{ $badge }}">{{ $stateLabel }}</span></td>
                            <td>{{ $level->latestMovement?->source_code ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">暂无序列号记录</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $serials->links() }}
    </div>
@endsection
