@extends('shop::layouts.app')

@section('title', '税率')
@section('heading', '税率')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $taxes->count() }} 条税率</p>
        <a href="{{ route('shop.taxes.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新增税率
        </a>
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
                <caption id="tax-table-caption" class="visually-hidden">税率列表</caption>
                <thead>
                    <tr>
                        <th scope="col">名称</th>
                        <th scope="col">编码</th>
                        <th scope="col">税率</th>
                        <th scope="col">计税方式</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($taxes as $tax)
                        <tr>
                            <td>{{ $tax->name }}</td>
                            <td>{{ $tax->code }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $tax->rate, 4), '0'), '.') }}%</td>
                            <td>{{ $tax->type === 'inclusive' ? '价内（含税）' : '价外（不含税）' }}</td>
                            <td>
                                @if ($tax->is_active)
                                    <span class="badge text-bg-success">启用</span>
                                @else
                                    <span class="badge text-bg-secondary">停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.taxes.edit', $tax) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $tax->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.taxes.destroy', $tax) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除税率 {{ $tax->name }} 吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $tax->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">暂无税率</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
