@extends('shop::layouts.app')

@section('title', '编辑价格表')
@section('heading', '编辑价格表')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="card mb-4" style="max-inline-size:36rem;">
        <form method="post" action="{{ route('shop.price-lists.update', $price_list) }}" aria-label="价格表基本信息表单">
            @include('shop::master.price-lists._form', ['method' => 'PATCH'])
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存修改</button>
                <a href="{{ route('shop.price-lists.index') }}" class="btn btn-outline-secondary">返回列表</a>
            </div>
        </form>
    </div>

    <div class="card mb-4">
        <div class="card-header">阶梯价格</div>
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">价格表阶梯价格行</caption>
                <thead>
                    <tr>
                        <th scope="col">商品</th>
                        <th scope="col" class="text-end">起订量</th>
                        <th scope="col" class="text-end">价格</th>
                        <th scope="col">有效期</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($price_list->items as $item)
                        <tr>
                            <td>{{ $item->product?->code }} · {{ $item->product?->name }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->min_qty, 4), '0'), '.') }}</td>
                            <td class="text-end">{{ number_format((float) $item->price, 2) }}</td>
                            <td>
                                {{ $item->valid_from?->toDateString() ?? '—' }}
                                ~ {{ $item->valid_to?->toDateString() ?? '长期' }}
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.price-lists.items.edit', [$price_list, $item]) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form method="post" action="{{ route('shop.price-lists.items.destroy', [$price_list, $item]) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除该阶梯价格吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">暂无阶梯价格</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">新增阶梯价格</div>
        <div class="card-body">
            <form method="post" action="{{ route('shop.price-lists.items.store', $price_list) }}" aria-label="新增阶梯价格表单">
                @include('shop::master.price-lists._item_form')
            </form>
        </div>
    </div>
@endsection
