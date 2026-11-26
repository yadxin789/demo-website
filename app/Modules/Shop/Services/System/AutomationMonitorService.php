<?php

namespace App\Modules\Shop\Services\System;

use App\Modules\Shop\Models\AutomationRun;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * 自动化运行监控：记录定时命令与队列任务的开始、结束、耗时与失败信息。
 * 记录只用于观测，不改变命令或队列本身的执行结果。
 */
class AutomationMonitorService
{
    private const MAX_ERROR_LENGTH = 1000;

    /** @var array<string, int> 当前进程内正在执行的命令记录 ID */
    private static array $commandRuns = [];

    /** @var array<string, int> 当前进程内正在执行的队列任务记录 ID */
    private static array $jobRuns = [];

    private static ?bool $tableReady = null;

    /** 只记录业务自动化命令，避免迁移、缓存、路由等日常命令污染监控数据。 */
    public function tracksCommand(string $command): bool
    {
        if (str_starts_with($command, 'erp:')) {
            return true;
        }

        return in_array($command, ['schedule:run', 'queue:restart', 'backup:create'], true);
    }

    public function commandStarted(CommandStarting $event): void
    {
        $name = $this->commandDisplayName($event->command, $event->input);

        $id = $this->safeCreate([
            'channel' => AutomationRun::CHANNEL_SCHEDULE,
            'name' => $name,
            'status' => AutomationRun::STATUS_RUNNING,
            'started_at' => Carbon::now(),
            'context' => ['exit_code' => null],
        ]);

        if ($id !== null) {
            self::$commandRuns[$event->command] = $id;
        }
    }

    public function commandFinished(CommandFinished $event): void
    {
        $id = self::$commandRuns[$event->command] ?? null;
        if ($id === null) {
            return;
        }

        unset(self::$commandRuns[$event->command]);

        $run = AutomationRun::find($id);
        if (! $run) {
            return;
        }

        $finishedAt = Carbon::now();
        $run->status = $event->exitCode === 0 ? AutomationRun::STATUS_SUCCESS : AutomationRun::STATUS_FAILED;
        $run->exit_code = $event->exitCode;
        $run->finished_at = $finishedAt;
        $run->duration_ms = $run->started_at
            ? max(0, (int) $run->started_at->diffInMilliseconds($finishedAt))
            : null;
        $run->save();
    }

    public function jobProcessing(JobProcessing $event): void
    {
        $name = $this->jobName($event);

        $id = $this->safeCreate([
            'channel' => AutomationRun::CHANNEL_QUEUE,
            'name' => $name,
            'status' => AutomationRun::STATUS_RUNNING,
            'started_at' => Carbon::now(),
            'context' => [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'attempts' => $event->job->attempts(),
            ],
        ]);

        if ($id !== null) {
            self::$jobRuns[$this->jobKey($event)] = $id;
        }
    }

    public function jobProcessed(JobProcessed $event): void
    {
        $run = $this->finishJob($event, AutomationRun::STATUS_SUCCESS, null);
        if ($run === null) {
            $this->safeCreate([
                'channel' => AutomationRun::CHANNEL_QUEUE,
                'name' => $this->jobName($event),
                'status' => AutomationRun::STATUS_SUCCESS,
                'started_at' => Carbon::now(),
                'finished_at' => Carbon::now(),
                'duration_ms' => 0,
                'context' => [
                    'connection' => $event->connectionName,
                    'queue' => $event->job->getQueue(),
                ],
            ]);
        }
    }

    public function jobFailed(JobFailed $event): void
    {
        $error = trim((string) $event->exception->getMessage());
        if ($error !== '') {
            $error = mb_substr($error, 0, self::MAX_ERROR_LENGTH);
        }

        $run = $this->finishJob($event, AutomationRun::STATUS_FAILED, $error ?: '队列任务执行失败');
        if ($run === null) {
            $this->safeCreate([
                'channel' => AutomationRun::CHANNEL_QUEUE,
                'name' => $this->jobName($event),
                'status' => AutomationRun::STATUS_FAILED,
                'started_at' => Carbon::now(),
                'finished_at' => Carbon::now(),
                'duration_ms' => 0,
                'error' => $error ?: '队列任务执行失败',
                'context' => [
                    'connection' => $event->connectionName,
                    'queue' => $event->job->getQueue(),
                    'attempts' => $event->job->attempts(),
                ],
            ]);
        }
    }

    private function finishJob(object $event, string $status, ?string $error): ?AutomationRun
    {
        $key = $this->jobKey($event);
        $id = self::$jobRuns[$key] ?? null;
        if ($id === null) {
            return null;
        }

        unset(self::$jobRuns[$key]);

        $run = AutomationRun::find($id);
        if (! $run) {
            return null;
        }

        $finishedAt = Carbon::now();
        $run->status = $status;
        $run->finished_at = $finishedAt;
        $run->duration_ms = $run->started_at
            ? max(0, (int) $run->started_at->diffInMilliseconds($finishedAt))
            : null;
        $run->error = $error;
        $run->save();

        return $run;
    }

    private function jobKey(object $event): string
    {
        return $event->connectionName . ':' . $event->job->getQueue() . ':' . $event->job->getJobId() . ':' . $this->jobName($event);
    }

    private function jobName(object $event): string
    {
        try {
            return $event->job->resolveName();
        } catch (\Throwable) {
            return 'unknown-job';
        }
    }

    private function commandDisplayName(string $command, mixed $input): string
    {
        $name = $command;

        try {
            $string = trim((string) $input);
            if ($string !== '') {
                // ArgvInput 的字符串形式会带命令名引号，如 "\"erp:ai-cycle-count\" --dry-run"。
                $string = preg_replace(
                    '/^["\']?' . preg_quote($command, '/') . '["\']?\s*/i',
                    '',
                    $string
                ) ?? $string;
                $string = trim($string);
                if ($string !== '') {
                    $name .= ' ' . $string;
                }
            }
        } catch (\Throwable) {
            // ArrayInput 等无法字符串化时只保留命令名。
        }

        return mb_substr($name, 0, 191);
    }

    /** @param array<string, mixed> $attributes */
    private function safeCreate(array $attributes): ?int
    {
        if (! $this->tableReady()) {
            return null;
        }

        try {
            return AutomationRun::create($attributes)->id;
        } catch (\Throwable) {
            return null;
        }
    }

    private function tableReady(): bool
    {
        if (self::$tableReady === null) {
            try {
                self::$tableReady = Schema::hasTable('automation_runs');
            } catch (\Throwable) {
                self::$tableReady = false;
            }
        }

        return self::$tableReady;
    }
}
