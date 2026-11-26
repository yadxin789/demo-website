@extends('shop::layouts.app')

@section('title', '批次效期预警')
@section('heading', '批次效期预警')

@section('content')
    @php
        $today = \Illuminate\Support\Carbon::today();
        $deadline = $today->copy()->addDays($thresholdDays);
        $tabs = [
            '' => '全部',
            'expired' => '已过期',
            'expiring' => '临期',
            'normal' => '正常',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">临期阈值：距效期 ≤ {{ $thresholdDays }} 天；共 {{ $lots->total() }} 个批次</p>
    </div>

    <div class="mb-3 d-flex gap-2 flex-wrap">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('shop.lots.index', $key === '' ? [] : ['status' => $key]) }}"
               class="btn btn-sm {{ ($status === $key || ($key === '' && $status === '')) ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="lot-table-caption" class="visually-hidden">批次效期清单</caption>
                <thead>
                    <tr>
                        <th scope="col">商品</th>
                        <th scope="col">批次号</th>
                        <th scope="col">供应商</th>
                        <th scope="col">入库日期</th>
                        <th scope="col">效期至</th>
                        <th scope="col" class="text-end">剩余天数</th>
                        <th scope="col" class="text-end">现存量</th>
                        <th scope="col">状态</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lots as $lot)
                        @php
                            $expiry = $lot->expiry_date ? \Illuminate\Support\Carbon::parse($lot->expiry_date) : null;
                            if ($expiry === null) {
                                $state = 'normal';
                                $stateLabel = '未设效期';
                                $badge = 'text-bg-secondary';
                                $days = null;
                            } elseif ($expiry->lt($today)) {
                                $state = 'expired';
                                $stateLabel = '已过期';
                                $badge = 'text-bg-danger';
                                $days = -$today->diffInDays($expiry, false);
                            } elseif ($expiry->lte($deadline)) {
                                $state = 'expiring';
                                $stateLabel = '临期';
                                $badge = 'text-bg-warning';
                                $days = $today->diffInDays($expiry, false);
                            } else {
                                $state = 'normal';
                                $stateLabel = '正常';
                                $badge = 'text-bg-success';
                                $days = $today->diffInDays($expiry, false);
                            }
                        @endphp
                        <tr>
                            <td>
                                {{ $lot->product?->code ?? '—' }}
                                <span class="text-muted small d-block">{{ $lot->product?->name ?? '商品已删除' }}</span>
                            </td>
                            <td>{{ $lot->lot_no }}</td>
                            <td>{{ $lot->supplier?->name ?? '—' }}</td>
                            <td>{{ optional($lot->receipt_date)->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ optional($lot->expiry_date)->format('Y-m-d') ?? '—' }}</td>
                            <td class="text-end">{{ $days === null ? '—' : $days }}</td>
                            <td class="text-end">{{ number_format((float) ($lot->on_hand ?? 0), 4) }}</td>
                            <td><span class="badge {{ $badge }}">{{ $stateLabel }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暂无批次记录</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $lots->links() }}
    </div>
@endsection
