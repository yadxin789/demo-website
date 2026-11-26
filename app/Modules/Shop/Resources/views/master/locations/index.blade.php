@extends('shop::layouts.app')

@section('title', '库位')
@section('heading', '库位')

@section('content')
    @php
        $typeLabels = ['storage' => '存储', 'picking' => '拣货', 'receiving' => '收货', 'shipping' => '发货'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $locations->count() }} 个库位</p>
        <div class="d-flex gap-2">
            <a href="{{ route('shop.locations.labels.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-barcode me-1"></i>库位标签
            </a>
            <a href="{{ route('shop.locations.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i>新增库位
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="location-table-caption" class="visually-hidden">库位列表</caption>
                <thead>
                    <tr>
                        <th scope="col">编码</th>
                        <th scope="col">库位条码</th>
                        <th scope="col">名称</th>
                        <th scope="col">所属仓库</th>
                        <th scope="col">父级库位</th>
                        <th scope="col">类型</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td>{{ $location->code }}</td>
                            <td><code>{{ $location->barcode ?? '—' }}</code></td>
                            <td>{{ $location->name }}</td>
                            <td>{{ $location->warehouse->name }}</td>
                            <td>{{ $location->parent?->name ?? '—' }}</td>
                            <td>{{ $typeLabels[$location->type] ?? $location->type }}</td>
                            <td>
                                @if ($location->is_active)
                                    <span class="badge text-bg-success">启用</span>
                                @else
                                    <span class="badge text-bg-secondary">停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.locations.edit', $location) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $location->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.locations.destroy', $location) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除库位 {{ $location->name }} 吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $location->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暂无库位</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
