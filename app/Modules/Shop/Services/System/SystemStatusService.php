<?php

namespace App\Modules\Shop\Services\System;

use App\Modules\Shop\Models\AutomationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 后台自动化状态汇总：调度器心跳、定时任务清单与最近运行结果、
 * 队列驱动/积压/失败任务、以及进程层面的 worker 存活检测。
 * 所有检测均为只读，不触发任务执行。
 */
class SystemStatusService
{
    /** 定时任务中文名；未列出的命令直接显示原始命令。 */
    private const TASK_LABELS = [
        'erp:scan-issues' => '经营巡检',
        'erp:ai-daily-briefing' => '每日经营简报',
        'erp:ai-replenishment' => '补货建议',
        'erp:ai-dunning' => '应收催收清单',
        'erp:ai-weekly-sales' => '周销售分析',
        'erp:ai-closing-check' => '月末结账体检',
        'erp:ai-data-quality' => '数据质量巡检',
        'erp:ai-auto-proposals --purchase' => '自动采购草稿提案',
        'erp:ai-auto-proposals --price' => '商品改价提案',
        'erp:ai-cycle-count' => '每日循环盘点',
        'erp:ai-accounting-review' => '会计复核',
        'erp:ai-bank-reconcile' => '银行对账助手',
    ];

    public function overview(): array
    {
        $tasks = $this->scheduledTasks();
        $scheduler = $this->schedulerStatus($tasks);
        $queue = $this->queueStatus();

        return [
            'scheduler' => $scheduler,
            'queue' => $queue,
            'tasks' => $tasks,
            'runs' => $this->recentRuns(),
            'failedRuns' => $this->recentFailedRuns(),
            'server' => $this->serverInfo(),
        ];
    }

    /** 从 artisan schedule:list --json 读取真实调度注册表，避免页面与 console.php 漂移。 */
    public function scheduledTasks(): array
    {
        $json = $this->scheduleJson();
        $lastRuns = AutomationRun::query()
            ->where('channel', AutomationRun::CHANNEL_SCHEDULE)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->limit(300)
            ->get()
            ->unique('name')
            ->keyBy('name');

        $now = Carbon::now();

        return collect($json)->map(function (array $task) use ($lastRuns, $now) {
            $command = $this->normalizeCommand((string) ($task['command'] ?? ''));
            $label = self::TASK_LABELS[$command] ?? $command;
            $last = $lastRuns->get($command);
            $nextDue = isset($task['next_due_date']) ? Carbon::parse($task['next_due_date']) : null;

            return [
                'command' => $command,
                'label' => $label,
                'expression' => $task['expression'] ?? '',
                'next_due_human' => $task['next_due_date_human'] ?? '—',
                'next_due_at' => $nextDue?->toDateTimeString(),
                'overdue' => $nextDue !== null && $nextDue->isPast(),
                'last_status' => $last?->status ?? 'never',
                'last_run_at' => $last?->finished_at?->toDateTimeString(),
                'last_duration_ms' => $last?->duration_ms,
                'last_error' => $last?->error,
                'last_exit_code' => $last?->exit_code,
            ];
        })->all();
    }

    private function schedulerStatus(array $tasks): array
    {
        $lastHeartbeat = AutomationRun::query()
            ->where('channel', AutomationRun::CHANNEL_SCHEDULE)
            ->where('name', 'schedule:run')
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        $running = $this->processContains('schedule:work');

        if (! $running && $lastHeartbeat) {
            $running = $lastHeartbeat->finished_at->gt(Carbon::now()->subMinutes(5));
        }

        $lastTask = collect($tasks)
            ->filter(fn ($task) => $task['last_run_at'] !== null)
            ->sortByDesc('last_run_at')
            ->first();

        return [
            'running' => $running,
            'mode' => $running ? '检测到调度进程或心跳' : '未检测到 schedule:work 进程或最近 5 分钟心跳',
            'last_heartbeat_at' => $lastHeartbeat?->finished_at?->toDateTimeString(),
            'last_task_at' => $lastTask['last_run_at'] ?? null,
            'last_task_label' => $lastTask['label'] ?? null,
            'task_count' => count($tasks),
            'failed_tasks' => collect($tasks)->where('last_status', AutomationRun::STATUS_FAILED)->count(),
            'stale_tasks' => collect($tasks)->where('overdue', true)->count(),
        ];
    }

    private function queueStatus(): array
    {
        $lastRun = AutomationRun::query()
            ->where('channel', AutomationRun::CHANNEL_QUEUE)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        $lastSuccess = AutomationRun::query()
            ->where('channel', AutomationRun::CHANNEL_QUEUE)
            ->where('status', AutomationRun::STATUS_SUCCESS)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        $running = $this->processContains('queue:work') || $this->processContains('queue:listen');
        if (! $running && $lastSuccess) {
            $running = $lastSuccess->finished_at->gt(Carbon::now()->subMinutes(10));
        }

        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $reserved = Schema::hasTable('jobs') ? DB::table('jobs')->whereNotNull('reserved_at')->count() : 0;
        $failedTotal = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $processedToday = AutomationRun::query()
            ->where('channel', AutomationRun::CHANNEL_QUEUE)
            ->where('status', AutomationRun::STATUS_SUCCESS)
            ->whereDate('finished_at', Carbon::today())
            ->count();

        return [
            'driver' => (string) config('queue.default'),
            'running' => $running,
            'pending' => $pending,
            'reserved' => $reserved,
            'failed_total' => $failedTotal,
            'processed_today' => $processedToday,
            'last_run_at' => $lastRun?->finished_at?->toDateTimeString(),
            'last_run_name' => $lastRun?->name,
            'failed_jobs' => $this->failedJobs(),
        ];
    }

    private function recentRuns(): array
    {
        return AutomationRun::query()
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (AutomationRun $run) => [
                'id' => $run->id,
                'channel' => $run->channel,
                'name' => $run->name,
                'status' => $run->status,
                'started_at' => $run->started_at?->toDateTimeString(),
                'finished_at' => $run->finished_at?->toDateTimeString(),
                'duration_ms' => $run->duration_ms,
                'exit_code' => $run->exit_code,
                'error' => $run->error,
            ])
            ->all();
    }

    private function recentFailedRuns(): array
    {
        return AutomationRun::query()
            ->where('status', AutomationRun::STATUS_FAILED)
            ->orderByDesc('finished_at')
            ->limit(10)
            ->get()
            ->map(fn (AutomationRun $run) => [
                'id' => $run->id,
                'channel' => $run->channel,
                'name' => $run->name,
                'finished_at' => $run->finished_at?->toDateTimeString(),
                'error' => $run->error,
            ])
            ->all();
    }

    private function failedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $payload = json_decode((string) $row->payload, true);
                $exception = trim((string) $row->exception);
                $firstLine = strtok($exception, "\n") ?: $exception;

                return [
                    'uuid' => $row->uuid,
                    'queue' => $row->queue,
                    'name' => $payload['displayName'] ?? $payload['job'] ?? '未知任务',
                    'failed_at' => (string) $row->failed_at,
                    'error' => mb_substr($firstLine, 0, 180),
                ];
            })
            ->all();
    }

    private function serverInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_time' => Carbon::now()->toDateTimeString(),
            'timezone' => config('app.timezone'),
            'queue_connection' => (string) config('queue.default'),
            'cache_store' => (string) config('cache.default'),
            'environment' => app()->environment(),
        ];
    }

    private function scheduleJson(): array
    {
        try {
            Artisan::call('schedule:list', ['--json' => true]);
            $output = Artisan::output();
            $decoded = json_decode(trim($output), true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeCommand(string $command): string
    {
        $command = trim($command);
        $command = preg_replace('/^php\s+artisan\s+/i', '', $command) ?? $command;

        return trim($command);
    }

    /** 进程检测：Windows 读取 php.exe 命令行，Linux 读取 ps；结果缓存 10 秒。 */
    private function processContains(string $needle): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        return Cache::remember('automation.process.' . md5($needle), 10, function () use ($needle) {
            $output = $this->processList();

            return $output !== '' && str_contains($output, $needle);
        });
    }

    private function processList(): string
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $exe = 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe';
                if (! is_file($exe)) {
                    return '';
                }
                $command = '"' . $exe . '" -NoProfile -NonInteractive -Command '
                    . '"Get-CimInstance Win32_Process | Where-Object { $_.Name -eq \'php.exe\' } | ForEach-Object { $_.CommandLine }"';
                $output = @shell_exec($command);
            } else {
                $output = @shell_exec('/bin/ps -eo args 2>/dev/null || ps -eo args');
            }

            return is_string($output) ? $output : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
