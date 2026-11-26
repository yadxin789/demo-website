@extends('shop::layouts.app')

@section('title', '审计日志')
@section('heading', '审计日志')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">共 {{ $logs->total() }} 条日志（只增不改不删，只读展示）</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="{{ route('shop.audit-logs.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-2">
                    <label for="filter-operator" class="form-label">操作人</label>
                    <select id="filter-operator" name="operator_id" class="form-select">
                        <option value="">全部</option>
                        @foreach ($operators as $operator)
                            <option value="{{ $operator->id }}" @selected((string) request('operator_id') === (string) $operator->id)>
                                {{ $operator->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="filter-module" class="form-label">模块</label>
                    <select id="filter-module" name="module" class="form-select">
                        <option value="">全部</option>
                        @foreach ($moduleLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('module') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="filter-action" class="form-label">动作</label>
                    <select id="filter-action" name="action" class="form-select">
                        <option value="">全部</option>
                        @foreach ($actionLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="filter-document" class="form-label">单据号</label>
                    <input type="text" id="filter-document" name="document_no" value="{{ request('document_no') }}" class="form-control" placeholder="模糊匹配">
                </div>
                <div class="col-12 col-md-2">
                    <label for="filter-keyword" class="form-label">消息关键字</label>
                    <input type="text" id="filter-keyword" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="模糊匹配">
                </div>
                <div class="col-6 col-md-1">
                    <label for="filter-from" class="form-label">日期起</label>
                    <input type="date" id="filter-from" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-6 col-md-1">
                    <label for="filter-to" class="form-label">日期止</label>
                    <input type="date" id="filter-to" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>筛选</button>
                    <a href="{{ route('shop.audit-logs.index') }}" class="btn btn-outline-secondary">重置</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption id="audit-log-caption" class="visually-hidden">审计日志列表</caption>
                <thead>
                    <tr>
                        <th scope="col">时间</th>
                        <th scope="col">操作人</th>
                        <th scope="col">模块</th>
                        <th scope="col">动作</th>
                        <th scope="col">单据号</th>
                        <th scope="col">记录</th>
                        <th scope="col">变更字段</th>
                        <th scope="col">旧值</th>
                        <th scope="col">新值</th>
                        <th scope="col">消息</th>
                        <th scope="col" class="text-end">对比</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $modelShort = $log->model ? class_basename($log->model) : '—';
                            $record = $log->record_id ? $modelShort . ' #' . $log->record_id : $modelShort;
                            $summary = $diffSummaries[$log->id] ?? ['count' => 0, 'rows' => []];
                            $first = $summary['rows'][0] ?? null;
                        @endphp
                        <tr>
                            <td>{{ optional($log->acted_at)->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $log->operator?->name ?? '系统/未知' }}</td>
                            <td>{{ $moduleLabels[$log->module] ?? $log->module ?? '—' }}</td>
                            <td>
                                <span class="badge text-bg-secondary">
                                    {{ $actionLabels[$log->action] ?? $log->action }}
                                </span>
                            </td>
                            <td>{{ $log->document_no ?? '—' }}</td>
                            <td>{{ $record }}</td>
                            <td>
                                @if ($first)
                                    <span class="small">{{ $first['label'] }}</span>
                                    @if ($summary['count'] > 1)
                                        <span class="badge text-bg-light border ms-1">共 {{ $summary['count'] }} 项</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($first)
                                    <span class="{{ $first['type'] === 'added' ? 'text-muted' : 'text-danger text-decoration-line-through' }}">{{ $first['old_text'] }}</span>
                                    @if ($summary['count'] > 1)<span class="text-muted"> …</span>@endif
                                @else
                                    <span class="text-danger">{{ $summary['fallback_old'] ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($first)
                                    <span class="{{ $first['type'] === 'removed' ? 'text-muted' : 'text-success fw-semibold' }}">{{ $first['new_text'] }}</span>
                                    @if ($summary['count'] > 1)<span class="text-muted"> …</span>@endif
                                @else
                                    <span class="text-success">{{ $summary['fallback_new'] ?? '—' }}</span>
                                @endif
                            </td>
                            <td>{{ $log->message ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('shop.audit-logs.show', $log) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-code-compare me-1" aria-hidden="true"></i>查看对比
                                    <span class="visually-hidden">{{ $record }} 字段对比</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">暂无审计日志</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
@endsection
