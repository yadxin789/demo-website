@extends('shop::layouts.app')

@section('title', "采购退货单 {$purchaseReturn->code}")
@section('heading', "采购退货单 {$purchaseReturn->code}")

@section('content')
    @php
        $statusLabels = ['draft' => '草稿', 'done' => '已退货', 'cancelled' => '已取消'];
        $badges = ['draft' => 'text-bg-secondary', 'done' => 'text-bg-success', 'cancelled' => 'text-bg-danger'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="text-muted mb-1">
                收货单：{{ $purchaseReturn->receipt?->code ?? '—' }} · 供应商：{{ $purchaseReturn->supplier?->name ?? '—' }} ·
                仓库：{{ $purchaseReturn->warehouse?->name ?? '—' }} · 退货日期：{{ optional($purchaseReturn->return_date)->format('Y-m-d') }}
            </p>
            <span class="badge {{ $badges[$purchaseReturn->status] ?? 'text-bg-secondary' }}">
                {{ $statusLabels[$purchaseReturn->status] ?? $purchaseReturn->status }}
            </span>
            <span class="text-muted small ms-2">操作人：{{ $purchaseReturn->operator?->name ?? '—' }}</span>
            @if ($purchaseReturn->reason)
                <span class="text-muted small ms-2">原因：{{ $purchaseReturn->reason }}</span>
            @endif
        </div>
        <a href="{{ route('shop.purchase-returns.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>返回列表
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="card p-0 mb-3">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">退货明细行</caption>
                <thead>
                    <tr>
                        <th scope="col">商品</th>
                        <th scope="col">库位</th>
                        <th scope="col">批次</th>
                        <th scope="col">序列号</th>
                        <th scope="col" class="text-end">退货数量</th>
                        <th scope="col" class="text-end">单位成本</th>
                        <th scope="col">原因</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseReturn->lines as $line)
                        <tr>
                            <td>
                                {{ $line->product?->code ?? '—' }}
                                <span class="text-muted small d-block">{{ $line->product?->name ?? '商品已删除' }}</span>
                            </td>
                            <td>{{ $line->location?->code ?? '—' }}</td>
                            <td>{{ $line->lot_no ?? '—' }}</td>
                            <td>{{ $line->serial_no ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $line->qty, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $line->unit_cost, 2) }}</td>
                            <td>{{ $line->reason ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($purchaseReturn->isEditable())
        <form method="post" action="{{ route('shop.purchase-returns.post', ['purchaseReturn' => $purchaseReturn->id]) }}"
              onsubmit="return confirm('确认过账该退货单？过账后将扣减库存并冲回应付，不可撤销。');">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check me-1"></i>过账退货
            </button>
        </form>
    @endif
@endsection
