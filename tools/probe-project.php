<?php

/**
 * 逐步探测 Project 模型的各条访问路径，定位 500 的真正来源。
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Pm\Models\Project;

function step(string $label, callable $fn): void
{
    try {
        $r = $fn();
        $out = is_scalar($r) ? (string) $r : (is_object($r) ? get_class($r) : gettype($r));
        echo "[OK]   {$label} => ".mb_substr($out, 0, 80)."\n";
    } catch (Throwable $e) {
        echo "[FAIL] {$label} => ".get_class($e).': '.$e->getMessage()."\n";
    }
}

$project = Project::query()->first();
if (! $project) {
    echo "没有项目数据\n";
    exit;
}

echo "project.id = {$project->id}\n";

step('原始 status 属性', fn () => $project->getRawOriginal('status'));
step('status cast 实例', fn () => $project->status);
step('status->label()', fn () => method_exists($project->status, 'label') ? $project->status->label() : 'no label');
step('creator', fn () => $project->creator?->name ?? 'null');
step('tasks count', fn () => $project->tasks()->count());
step('tasks 第一条', fn () => $project->tasks()->first()?->name ?? 'null');
step('participants()', function () use ($project) {
    if (! method_exists($project, 'participants')) {
        return 'no method';
    }
    $p = $project->participants();

    return is_countable($p) ? count($p) : get_class($p);
});
step('logs', fn () => method_exists($project, 'logs') ? $project->logs()->count() : 'no logs');
step('progress 计算', fn () => $project->progress);
step('状态统计', fn () => json_encode(\App\Modules\Pm\Models\Task::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')));
