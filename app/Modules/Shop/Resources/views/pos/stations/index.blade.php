@extends('shop::layouts.app')

@section('title', '收银台配置')
@section('heading', '收银台配置')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $stations->count() }} 台收银终端</p>
        <a href="{{ route('shop.pos.stations.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>新建收银台
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
                <caption id="station-caption" class="visually-hidden">收银台列表</caption>
                <thead>
                    <tr>
                        <th scope="col">编码</th>
                        <th scope="col">名称</th>
                        <th scope="col">仓库</th>
                        <th scope="col">打印机</th>
                        <th scope="col">纸宽</th>
                        <th scope="col">编码</th>
                        <th scope="col">状态</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stations as $station)
                        <tr>
                            <td>{{ $station->code }}</td>
                            <td>{{ $station->name }}</td>
                            <td>{{ $station->warehouse?->name ?? '—' }}</td>
                            <td>
                                {{ $station->printer_driver }}
                                @if ($station->printer_address)
                                    <div class="small text-muted">{{ $station->printer_address }}@if ($station->printer_port):{{ $station->printer_port }}@endif</div>
                                @endif
                            </td>
                            <td>{{ $station->paper_width }}mm</td>
                            <td>{{ $station->encoding }}</td>
                            <td>
                                <span class="badge {{ $station->isActive() ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $station->isActive() ? '启用' : '停用' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('shop.pos.stations.edit', $station) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $station->code }}</span>
                                </a>
                                <form method="post" action="{{ route('shop.pos.stations.test-print', $station) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="打印测试页">
                                        <i class="fa-solid fa-print"></i><span class="visually-hidden">打印测试页 {{ $station->code }}</span>
                                    </button>
                                </form>
                                <form method="post" action="{{ route('shop.pos.stations.test-drawer', $station) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="弹出钱箱">
                                        <i class="fa-solid fa-box-open"></i><span class="visually-hidden">弹出钱箱 {{ $station->code }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暂无收银台</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
