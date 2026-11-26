@extends('shop::layouts.app')

@section('title', '自动化监控')
@section('heading', '自动化监控')

@section('content')
    @php
        $statusMeta = [
            'success' => ['label' => '成功', 'badge' => 'success'],
            'failed' => ['label' => '失败', 'badge' => 'danger'],
            'running' => ['label' => '运行中', 'badge' => 'warning'],
            'never' => ['label' => '未运行', 'badge' => 'secondary'],
        ];
        $channelMeta = ['schedule' => '定时任务', 'queue' => '队列任务'];
        $duration = function ($ms) {
            if ($ms === null) {
                return '—';
            }
            return $ms >= 1000 ? number_format($ms / 1000, 1) . ' 秒' : $ms . ' 毫秒';
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="text-muted small">
            <i class="fa-solid fa-circle-info me-1"></i>
            本页只读检测调度器、队列与自动化命令，不会触发任务执行。页面数据来自实际运行记录与服务器进程。
        </div>
        <a href="{{ route('shop.automation.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-rotate me-1"></i>刷新
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="card p-3 h-100 border-{{ $scheduler['running'] ? 'success' : 'danger' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">定时调度器</div>
                        <div class="fs-5 fw-bold">{{ $scheduler['running'] ? '运行中' : '未检测到' }}</div>
                    </div>
                    <i class="fa-solid fa-clock fa-2x text-{{ $scheduler['running'] ? 'success' : 'danger' }}"></i>
                </div>
                <div class="small text-muted mt-2">
                    最近心跳：{{ $scheduler['last_heartbeat_at'] ?: '暂无 schedule:run 记录' }}
                </div>
                @if (! $scheduler['running'])
                    <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small">
                        定时任务不会自动执行。开发环境请运行 <code>php artisan schedule:work</code>；生产环境请配置 crontab 每分钟执行 <code>schedule:run</code>。
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card p-3 h-100 border-{{ $queue['running'] ? 'success' : 'danger' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">队列处理器</div>
                        <div class="fs-5 fw-bold">{{ $queue['running'] ? '运行中' : '未检测到' }}</div>
                    </div>
                    <i class="fa-solid fa-tasks fa-2x text-{{ $queue['running'] ? 'success' : 'danger' }}"></i>
                </div>
                <div class="small text-muted mt-2">
                    驱动 <code>{{ $queue['driver'] }}</code> · 最近处理：{{ $queue['last_run_at'] ?: '暂无记录' }}
                </div>
                @if (! $queue['running'])
                    <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small">
                        异步任务会持续积压。请运行 <code>php artisan queue:work</code>（生产建议使用守护进程）。
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card p-3 h-100">
                <div class="text-muted small">队列积压</div>
                <div class="fs-5 fw-bold {{ $queue['pending'] > 0 ? 'text-warning' : 'text-success' }}">
                    {{ number_format($queue['pending']) }} 个待处理
                </div>
                <div class="small text-muted mt-2">
                    正在执行 {{ $queue['reserved'] }} · 今日成功 {{ $queue['processed_today'] }}
                </div>
                <div class="small mt-1">
                    失败任务总数：
                    <span class="fw-bold {{ $queue['failed_total'] > 0 ? 'text-danger' : 'text-success' }}">{{ $queue['failed_total'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card p-3 h-100">
                <div class="text-muted small">注册定时任务</div>
                <div class="fs-5 fw-bold">{{ $scheduler['task_count'] }} 项</div>
                <div class="small text-muted mt-2">
                    最近执行：{{ $scheduler['last_task_label'] ?: '暂无记录' }}
                </div>
                <div class="small mt-1">
                    上次失败 {{ $scheduler['failed_tasks'] }} 项 · 已过期待执行 {{ $scheduler['stale_tasks'] }} 项
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3 p-0">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-calendar-check me-1"></i>定时任务清单</span>
            <span class="text-muted small">来源：<code>php artisan schedule:list --json</code></span>
        </div>
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>任务</th>
                        <th>执行规则</th>
                        <th>下次执行</th>
                        <th>最近运行</th>
                        <th>耗时</th>
                        <th>结果</th>
                        <th>退出码</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks as $task)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $task['label'] }}</div>
                                <div class="text-muted small"><code>{{ $task['command'] }}</code></div>
                            </td>
                            <td><code>{{ $task['expression'] }}</code></td>
                            <td>
                                {{ $task['next_due_human'] }}
                                @if ($task['overdue'])
                                    <span class="badge bg-danger ms-1">已过期</span>
                                @endif
                                <div class="text-muted small">{{ $task['next_due_at'] }}</div>
                            </td>
                            <td>{{ $task['last_run_at'] ?: '—' }}</td>
                            <td>{{ $duration($task['last_duration_ms']) }}</td>
                            <td>
                                @php $meta = $statusMeta[$task['last_status']] ?? $statusMeta['never']; @endphp
                                <span class="badge bg-{{ $meta['badge'] }}">{{ $meta['label'] }}</span>
                                @if ($task['last_error'])
                                    <div class="text-danger small mt-1" title="{{ $task['last_error'] }}">{{ \Illuminate\Support\Str::limit($task['last_error'], 80) }}</div>
                                @endif
                            </td>
                            <td>{{ $task['last_exit_code'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">未能读取调度清单，请检查 artisan 控制台是否可用</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card p-0 h-100">
                <div class="card-header"><i class="fa-solid fa-list-check me-1"></i>近期自动化运行</div>
                <div class="table-wrapper">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>类型</th><th>名称</th><th>开始</th><th>耗时</th><th>结果</th></tr></thead>
                        <tbody>
                            @forelse ($runs as $run)
                                @php $meta = $statusMeta[$run['status']] ?? $statusMeta['never']; @endphp
                                <tr>
                                    <td>{{ $channelMeta[$run['channel']] ?? $run['channel'] }}</td>
                                    <td>
                                        <div><code>{{ $run['name'] }}</code></div>
                                        @if ($run['error'])
                                            <div class="text-danger small">{{ \Illuminate\Support\Str::limit($run['error'], 100) }}</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ $run['started_at'] ?: '—' }}</td>
                                    <td>{{ $duration($run['duration_ms']) }}</td>
                                    <td><span class="badge bg-{{ $meta['badge'] }}">{{ $meta['label'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">暂无自动化运行记录；任务执行后将自动出现在这里</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card p-0 h-100">
                <div class="card-header d-flex justify-content-between">
                    <span><i class="fa-solid fa-triangle-exclamation me-1"></i>最近失败</span>
                    <span class="text-muted small">最多 10 条</span>
                </div>
                <div class="table-wrapper">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>名称</th><th>时间</th><th>错误</th></tr></thead>
                        <tbody>
                            @forelse ($failedRuns as $run)
                                <tr>
                                    <td class="small"><code>{{ $run['name'] }}</code></td>
                                    <td class="small">{{ $run['finished_at'] ?: '—' }}</td>
                                    <td class="small text-danger">{{ \Illuminate\Support\Str::limit($run['error'] ?: '任务失败', 120) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">暂无失败的自动化运行</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-0 mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-bug me-1"></i>队列失败任务</span>
            <span class="text-muted small">数据表 <code>failed_jobs</code></span>
        </div>
        <div class="table-wrapper">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>任务类</th><th>队列</th><th>失败时间</th><th>错误摘要</th></tr></thead>
                <tbody>
                    @forelse ($queue['failed_jobs'] as $job)
                        <tr>
                            <td><code class="small">{{ $job['name'] }}</code><div class="text-muted small">{{ \Illuminate\Support\Str::limit($job['uuid'], 12) }}</div></td>
                            <td>{{ $job['queue'] }}</td>
                            <td class="small">{{ $job['failed_at'] }}</td>
                            <td class="small text-danger">{{ $job['error'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">暂无失败队列任务</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-3">
        <div class="row g-2 small">
            <div class="col-md-4"><span class="text-muted">PHP 版本：</span>{{ $server['php_version'] }}</div>
            <div class="col-md-4"><span class="text-muted">Laravel：</span>{{ $server['laravel_version'] }}</div>
            <div class="col-md-4"><span class="text-muted">运行环境：</span>{{ $server['environment'] }}</div>
            <div class="col-md-4"><span class="text-muted">服务器时间：</span>{{ $server['server_time'] }}</div>
            <div class="col-md-4"><span class="text-muted">时区：</span>{{ $server['timezone'] }}</div>
            <div class="col-md-4"><span class="text-muted">队列 / 缓存：</span>{{ $server['queue_connection'] }} / {{ $server['cache_store'] }}</div>
        </div>
    </div>
@endsection
