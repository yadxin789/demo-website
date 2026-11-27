@extends('system.layout')

@section('title', '运维监控')
@section('sub', '运行时、数据库与日志的只读体检')

@section('content')
<div class="card">
    <div class="card-header">体检项</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th style="width:90px;">类别</th><th>项目</th><th>值</th><th class="text-end" style="width:80px;">结论</th></tr></thead>
            <tbody>
                @foreach ($checks as [$cat, $name, $value, $state])
                    <tr>
                        <td class="small text-muted">{{ $cat }}</td>
                        <td class="small">{{ $name }}</td>
                        <td class="font-monospace small">{{ $value }}</td>
                        <td class="text-end">
                            @if ($state === 'ok')
                                <span class="badge text-bg-success">正常</span>
                            @elseif ($state === 'warn')
                                <span class="badge text-bg-warning">注意</span>
                            @else
                                <span class="badge text-bg-secondary">提示</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">记录最多的数据表</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>表</th><th class="text-end">行数</th></tr></thead>
                    <tbody>
                        @foreach ($topTables as $name => $count)
                            <tr>
                                <td class="font-monospace small">{{ $name }}</td>
                                <td class="text-end">{{ number_format($count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">日志尾部（最近 10 行）</div>
            <div class="card-body">
                @if (empty($logTail))
                    <div class="small text-muted">暂无日志。</div>
                @else
                    <pre class="small mb-0" style="max-height:340px;overflow:auto;white-space:pre-wrap;">{{ implode("\n", $logTail) }}</pre>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
