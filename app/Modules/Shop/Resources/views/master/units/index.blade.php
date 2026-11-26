@extends('shop::layouts.app')

@section('title', '计量单位')
@section('heading', '计量单位')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $units->count() }} 个单位</p>
        <a href="{{ route('shop.units.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新增单位
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
                <caption id="unit-table-caption" class="visually-hidden">计量单位列表</caption>
                <thead>
                    <tr>
                        <th scope="col">名称</th>
                        <th scope="col">编码</th>
                        <th scope="col">小数位</th>
                        <th scope="col">基础单位</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($units as $unit)
                        <tr>
                            <td>{{ $unit->name }}</td>
                            <td>{{ $unit->code }}</td>
                            <td>{{ $unit->precision }}</td>
                            <td>
                                @if ($unit->is_base)
                                    <span class="badge text-bg-primary">是</span>
                                @else
                                    <span class="text-muted">否</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.units.edit', $unit) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $unit->name }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.units.destroy', $unit) }}" class="d-inline"
                                      onsubmit="return confirm('确定删除单位 {{ $unit->name }} 吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i><span class="visually-hidden">删除 {{ $unit->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">暂无计量单位</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
