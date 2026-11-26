@extends('shop::layouts.app')

@section('title', '审计字段对比')
@section('heading', '审计字段对比')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <p class="text-muted mb-1">
                {{ $log->document_no ? '单据 ' . $log->document_no . ' · ' : '' }}
                {{ $moduleLabels[$log->module] ?? $log->module ?? '—' }} ·
                {{ $actionLabels[$log->action] ?? $log->action }}
                · 共 {{ count($entries) }} 个字段发生变化
            </p>
            <p class="text-muted mb-0 small">
                同一操作产生的多条字段日志已合并展示；红色为变更前，绿色为变更后。
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('shop.audit-logs.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>返回列表
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 text-muted mb-3">变更概要</h2>
            <dl class="row mb-0 small">
                <dt class="col-6 col-md-3">操作时间</dt>
                <dd class="col-6 col-md-3">{{ optional($log->acted_at)->format('Y-m-d H:i:s') ?? '—' }}</dd>
                <dt class="col-6 col-md-3">操作人</dt>
                <dd class="col-6 col-md-3">{{ $log->operator?->name ?? '系统/未知' }}</dd>
                <dt class="col-6 col-md-3">记录</dt>
                <dd class="col-6 col-md-3">
                    {{ $log->model ? class_basename($log->model) : '—' }}
                    {{ $log->record_id ? '#' . $log->record_id : '' }}
                </dd>
                <dt class="col-6 col-md-3">来源</dt>
                <dd class="col-6 col-md-3">{{ $log->source ?? '—' }}</dd>
                <dt class="col-6 col-md-3">消息</dt>
                <dd class="col-6 col-md-9">{{ $log->message ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">字段级变更对比：字段、变更类型、变更前值、变更后值</caption>
                <thead>
                    <tr>
                        <th scope="col">字段</th>
                        <th scope="col">变更类型</th>
                        <th scope="col">变更前</th>
                        <th scope="col">变更后</th>
                        <th scope="col">日志时间</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <th scope="row" class="fw-normal">
                                <span>{{ $entry['label'] }}</span>
                                @if ($entry['label'] !== $entry['field'])
                                    <code class="small text-muted d-block">{{ $entry['field'] }}</code>
                                @endif
                            </th>
                            <td>
                                @php
                                    $typeClass = match ($entry['type']) {
                                        'added' => 'text-bg-success',
                                        'removed' => 'text-bg-danger',
                                        default => 'text-bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $typeClass }}">{{ $typeLabels[$entry['type']] ?? $entry['type'] }}</span>
                            </td>
                            <td>
                                @if ($entry['type'] === 'added')
                                    <span class="text-muted">（原无此项）</span>
                                @else
                                    <span class="text-danger text-decoration-line-through">{{ $entry['old_text'] }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($entry['type'] === 'removed')
                                    <span class="text-muted">（已删除）</span>
                                @else
                                    <span class="text-success fw-semibold">{{ $entry['new_text'] }}</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ optional($entry['log']->acted_at)->format('H:i:s') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">该次操作没有可逐字段对比的变更</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h2 class="h6 text-muted mb-3">关联日志（同一次操作）</h2>
            <ul class="list-unstyled mb-0 small">
                @foreach ($changeSet as $item)
                    <li class="d-flex gap-2 py-1 border-bottom">
                        <span class="text-muted">{{ optional($item->acted_at)->format('Y-m-d H:i:s') }}</span>
                        <span>{{ $actionLabels[$item->action] ?? $item->action }}</span>
                        <span class="text-muted">{{ $item->field ?? '整单/整对象' }}</span>
                        <span class="ms-auto text-muted">#{{ $item->id }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endsection
