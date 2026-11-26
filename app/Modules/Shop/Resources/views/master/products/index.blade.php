@extends('shop::layouts.app')

@section('title', '商品')
@section('heading', '商品')

@section('content')
    @php
        $traceLabels = ['none' => '不追踪', 'lot' => '批次', 'serial' => '序列号'];
        $methodLabels = ['standard' => '标准成本', 'avco' => '移动加权', 'fifo' => '先进先出'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $products->count() }} 件商品</p>
        <div class="d-flex gap-2">
            <a href="{{ route('shop.exchange.import', 'products') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-upload me-1"></i>导入
            </a>
            <a href="{{ route('shop.exchange.export', 'products') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-file-export me-1"></i>导出
            </a>
            <a href="{{ route('shop.products.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i>新增商品
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="product-table-caption" class="visually-hidden">商品列表</caption>
                <thead>
                    <tr>
                        <th scope="col">图片</th>
                        <th scope="col">编码</th>
                        <th scope="col">名称</th>
                        <th scope="col">类别</th>
                        <th scope="col">单位</th>
                        <th scope="col" class="text-end">零售价</th>
                        <th scope="col" class="text-end">标准成本</th>
                        <th scope="col">追溯</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                @if ($product->hasImage())
                                    <a href="{{ $product->imageUrl('full') }}" target="_blank" rel="noopener"
                                       title="查看 {{ $product->name }} 大图">
                                        <img src="{{ $product->imageUrl('thumb') }}" alt="{{ $product->name }}"
                                             width="40" height="40" class="rounded" style="object-fit:cover;" loading="lazy">
                                    </a>
                                @else
                                    <span class="text-muted" aria-label="暂无图片"><i class="fa-regular fa-image" aria-hidden="true"></i></span>
                                @endif
                            </td>
                            <td>{{ $product->code }}</td>
                            <td>
                                {{ $product->name }}
                                @if ($product->barcode)
                                    <span class="text-muted small d-block">{{ $product->barcode }}</span>
                                @endif
                            </td>
                            <td>{{ $product->category?->name ?? '—' }}</td>
                            <td>{{ $product->unit?->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $product->list_price, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $product->standard_cost, 2) }}</td>
                            <td>{{ $traceLabels[$product->traceability] ?? $product->traceability }}</td>
                            <td>
                                @if ($product->is_active)
                                    <span class="badge text-bg-success">启用</span>
                                @else
                                    <span class="badge text-bg-secondary">停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.products.edit', $product) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $product->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.products.destroy', $product) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除商品 {{ $product->name }} 吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $product->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">暂无商品</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
