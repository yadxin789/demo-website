@extends('shop::layouts.app')

@section('title', '毛利与存货估值')
@section('heading', '毛利与存货估值报表')

@section('content')
    <form method="get" action="{{ route('shop.profit-stock.index') }}" class="row g-2 mb-3 align-items-end" aria-label="报表筛选">
        <div class="col-12 col-md-3">
            <label for="product_id" class="form-label">商品</label>
            <select id="product_id" name="product_id" class="form-select">
                <option value="">全部商品</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected($productId === $product->id)>
                        {{ $product->code }} · {{ $product->name }}
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
            <a href="{{ route('shop.profit-stock.index') }}" class="btn btn-outline-secondary">重置</a>
        </div>
    </form>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('shop.profit-stock.export', array_filter(['section' => 'profit', 'product_id' => $productId, 'start' => $start, 'end' => $end])) }}"
           class="btn btn-outline-secondary">
            <i class="fa-solid fa-file-export me-1"></i>导出毛利分析 CSV
        </a>
        <a href="{{ route('shop.profit-stock.export', array_filter(['section' => 'stock', 'product_id' => $productId])) }}"
           class="btn btn-outline-secondary">
            <i class="fa-solid fa-file-export me-1"></i>导出存货估值 CSV
        </a>
        <span class="text-muted small align-self-center">导出为 UTF-8 CSV（带 BOM），导出动作写入审计日志。</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">存货估值合计（结存 × 标准成本）</div>
                <div class="fs-5 fw-bold">{{ number_format($totalValuation, 4) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">销售收入（已发货净额）</div>
                <div class="fs-5 fw-bold">{{ number_format($revenue, 4) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">毛利（收入 − 标准成本）</div>
                <div class="fs-5 fw-bold {{ $grossProfit >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($grossProfit, 4) }}（{{ number_format($margin, 2) }}%）
                </div>
            </div>
        </div>
    </div>

    <div class="card p-0 mb-3">
        <div class="card-header">存货估值</div>
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">存货估值明细</caption>
                <thead>
                    <tr>
                        <th scope="col">编码</th>
                        <th scope="col">商品名称</th>
                        <th scope="col" class="text-end">结存数量</th>
                        <th scope="col" class="text-end">标准成本</th>
                        <th scope="col" class="text-end">估值金额</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockRows as $row)
                        <tr>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-end">{{ number_format($row['qty'], 4) }}</td>
                            <td class="text-end">{{ number_format($row['standard_cost'], 4) }}</td>
                            <td class="text-end">{{ number_format($row['valuation'], 4) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">暂无结存商品</td></tr>
                    @endforelse
                </tbody>
                @if ($stockRows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="2" scope="row" class="text-end">合计</th>
                            <th class="text-end">{{ number_format($totalQty, 4) }}</th>
                            <th></th>
                            <th class="text-end">{{ number_format($totalValuation, 4) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="card p-0">
        <div class="card-header">毛利分析（已发货订单）</div>
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">毛利分析明细</caption>
                <thead>
                    <tr>
                        <th scope="col">编码</th>
                        <th scope="col">商品名称</th>
                        <th scope="col" class="text-end">销量</th>
                        <th scope="col" class="text-end">收入</th>
                        <th scope="col" class="text-end">标准成本</th>
                        <th scope="col" class="text-end">毛利</th>
                        <th scope="col" class="text-end">毛利率</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profitRows as $row)
                        <tr>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-end">{{ number_format($row['qty'], 4) }}</td>
                            <td class="text-end">{{ number_format($row['revenue'], 4) }}</td>
                            <td class="text-end">{{ number_format($row['cost'], 4) }}</td>
                            <td class="text-end">{{ number_format($row['grossProfit'], 4) }}</td>
                            <td class="text-end">{{ number_format($row['margin'], 2) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">所选期间暂无已发货销售</td></tr>
                    @endforelse
                </tbody>
                @if ($profitRows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="3" scope="row" class="text-end">合计</th>
                            <th class="text-end">{{ number_format($revenue, 4) }}</th>
                            <th class="text-end">{{ number_format($cost, 4) }}</th>
                            <th class="text-end">{{ number_format($grossProfit, 4) }}</th>
                            <th class="text-end">{{ number_format($margin, 2) }}%</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
