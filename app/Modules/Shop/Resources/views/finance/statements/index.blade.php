@extends('shop::layouts.app')

@section('title', $type === 'supplier' ? '供应商对账单' : '客户对账单')
@section('heading', $type === 'supplier' ? '供应商对账单' : '客户对账单')

@section('content')
    @php
        $isSupplier = $type === 'supplier';
        $billStatus = [
            'draft' => '草稿', 'open' => '未付', 'partial' => '部分付款',
            'paid' => '已结清', 'void' => '已作废',
        ];
        $invoiceStatus = [
            'draft' => '草稿', 'open' => '未收', 'partial' => '部分收款',
            'paid' => '已结清', 'void' => '已作废',
        ];
        $paymentStatus = ['draft' => '草稿', 'confirmed' => '已确认'];
        $partyLabel = $isSupplier ? '供应商' : '客户';
        $partyId = $isSupplier ? $supplierId : $customerId;
    @endphp

    <div class="d-flex gap-2 mb-3" role="group" aria-label="对账单类型切换">
        <a href="{{ route('shop.statements.index', ['type' => 'customer']) }}"
           class="btn {{ $isSupplier ? 'btn-outline-secondary' : 'btn-primary' }}">客户对账单</a>
        <a href="{{ route('shop.statements.index', ['type' => 'supplier']) }}"
           class="btn {{ $isSupplier ? 'btn-primary' : 'btn-outline-secondary' }}">供应商对账单</a>
    </div>

    <form method="get" action="{{ route('shop.statements.index') }}" class="row g-2 mb-3 align-items-end" aria-label="对账筛选">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="col-12 col-md-3">
            <label for="party_id" class="form-label">{{ $partyLabel }}</label>
            <select id="party_id" name="{{ $isSupplier ? 'supplier_id' : 'customer_id' }}" class="form-select">
                <option value="">全部{{ $partyLabel }}</option>
                @foreach ($isSupplier ? $suppliers : $customers as $party)
                    <option value="{{ $party->id }}" @selected($partyId === $party->id)>
                        {{ $party->code }} · {{ $party->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label for="start" class="form-label">起始日期</label>
            <input type="date" id="start" name="start" value="{{ $start }}" class="form-control">
        </div>
        <div class="col-12 col-md-2">
            <label for="end" class="form-label">截止日期</label>
            <input type="date" id="end" name="end" value="{{ $end }}" class="form-control">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>查询</button>
            <a href="{{ route('shop.statements.index', ['type' => $type]) }}" class="btn btn-outline-secondary">重置</a>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">{{ $isSupplier ? '应付账单合计' : '应收发票合计' }}</div>
                <div class="fs-5 fw-bold">{{ number_format($invoiceTotal, 4) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">{{ $isSupplier ? '已付款项合计' : '已收款项合计' }}</div>
                <div class="fs-5 fw-bold">{{ number_format($paymentTotal, 4) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">{{ $isSupplier ? '应付余额' : '应收余额' }}</div>
                <div class="fs-5 fw-bold {{ $balance > 0 ? 'text-danger' : 'text-success' }}">
                    {{ number_format($balance, 4) }}
                </div>
            </div>
        </div>
    </div>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">{{ $partyLabel }}对账明细</caption>
                <thead>
                    <tr>
                        <th scope="col">日期</th>
                        <th scope="col">单据号</th>
                        <th scope="col">类型</th>
                        <th scope="col" class="text-end">{{ $isSupplier ? '应付' : '应收' }}</th>
                        <th scope="col" class="text-end">{{ $isSupplier ? '已付' : '已收' }}</th>
                        <th scope="col">状态</th>
                        <th scope="col">备注</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['date'] ?: '—' }}</td>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $isSupplier ? ($row['type'] === 'bill' ? '账单' : '付款') : ($row['type'] === 'invoice' ? '发票' : '收款') }}</td>
                            <td class="text-end">{{ $isSupplier
                                ? ($row['credit'] > 0 ? number_format($row['credit'], 4) : '—')
                                : ($row['debit'] > 0 ? number_format($row['debit'], 4) : '—') }}</td>
                            <td class="text-end">{{ $isSupplier
                                ? ($row['debit'] > 0 ? number_format($row['debit'], 4) : '—')
                                : ($row['credit'] > 0 ? number_format($row['credit'], 4) : '—') }}</td>
                            <td>
                                @if (($isSupplier && $row['type'] === 'bill') || (! $isSupplier && $row['type'] === 'invoice'))
                                    {{ ($isSupplier ? $billStatus : $invoiceStatus)[$row['status']] ?? $row['status'] }}
                                @else
                                    {{ $paymentStatus[$row['status']] ?? $row['status'] }}
                                @endif
                            </td>
                            <td>{{ $row['memo'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                所选期间暂无{{ $isSupplier ? '账单或付款' : '发票或收款' }}记录
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="3" scope="row" class="text-end">合计</th>
                            <th class="text-end">{{ number_format($invoiceTotal, 4) }}</th>
                            <th class="text-end">{{ number_format($paymentTotal, 4) }}</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
