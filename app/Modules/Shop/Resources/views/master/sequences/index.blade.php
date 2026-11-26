@extends('shop::layouts.app')

@section('title', '单据编号规则')
@section('heading', '单据编号规则')

@section('content')
    @php
        $periodLabels = ['none' => '不重置', 'day' => '按日重置', 'month' => '按月重置', 'year' => '按年重置'];
    @endphp

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="sequence-caption" class="visually-hidden">单据编号规则列表</caption>
                <thead>
                    <tr>
                        <th scope="col">规则名称</th>
                        <th scope="col">编码</th>
                        <th scope="col">前缀</th>
                        <th scope="col">后缀</th>
                        <th scope="col" class="text-end">位数</th>
                        <th scope="col">期间重置</th>
                        <th scope="col">下一编号预览</th>
                        <th scope="col" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sequences as $sequence)
                        <tr>
                            <td>{{ $sequence->name }}</td>
                            <td>{{ $sequence->code }}</td>
                            <td>{{ $sequence->prefix ?: '—' }}</td>
                            <td>{{ $sequence->suffix ?: '—' }}</td>
                            <td class="text-end">{{ $sequence->padding }}</td>
                            <td>{{ $periodLabels[$sequence->period_mode] ?? $sequence->period_mode }}</td>
                            <td><code>{{ $previews[$sequence->id] ?? '' }}</code></td>
                            <td class="text-end">
                                <a href="{{ route('shop.sequences.edit', $sequence) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i><span class="visually-hidden">编辑 {{ $sequence->name }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暂无编号规则</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
