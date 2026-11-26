<?php

namespace App\Modules\Pm\Models;


/**
 * 状态机 label 覆盖表
 * 存储用户对状态机类的自定义标签名。
 * state_class: 状态机完整类名（唯一键）
 * custom_label: 自定义标签名，为 null 时使用状态机默认 label()
 */
class Status extends BaseModel
{
    protected $fillable = ['state_class', 'custom_label'];

    protected $table = 'statuses';

    /**
     * 获取所有状态（从状态机类 + 自定义 label 合并）
     * @param string|null $type 'project' 或 'task'，为 null 返回全部
     * @return \Illuminate\Support\Collection
     */
    public static function allStates(?string $type = null): \Illuminate\Support\Collection
    {
        $projectStates = [
            \App\Modules\Pm\States\Project\InProgress::class,
            \App\Modules\Pm\States\Project\Completed::class,
            \App\Modules\Pm\States\Project\Overdue::class,
            \App\Modules\Pm\States\Project\OverdueCompleted::class,
            \App\Modules\Pm\States\Project\Suspended::class,
            \App\Modules\Pm\States\Project\NotStarted::class,
        ];

        $taskStates = [
            \App\Modules\Pm\States\Task\Pending::class,
            \App\Modules\Pm\States\Task\InProgress::class,
            \App\Modules\Pm\States\Task\ForAudit::class,
            \App\Modules\Pm\States\Task\Completed::class,
            \App\Modules\Pm\States\Task\Rejected::class,
            \App\Modules\Pm\States\Task\Overdue::class,
            \App\Modules\Pm\States\Task\OverdueCompleted::class,
        ];

        $stateClasses = match ($type) {
            'project' => $projectStates,
            'task' => $taskStates,
            default => array_merge($projectStates, $taskStates),
        };

        // 从数据库读取自定义 label 覆盖
        $overrides = self::whereIn('state_class', $stateClasses)
            ->pluck('custom_label', 'state_class');

        return collect($stateClasses)->map(function ($class) use ($overrides) {
            $defaultLabel = self::getStateDefaultLabel($class);
            $defaultColor = self::getStateDefaultColor($class);
            $type = str_contains($class, '\\Project\\') ? 'project' : 'task';

            return [
                'state_class' => $class,
                'name' => $overrides[$class] ?? $defaultLabel,
                'custom_label' => $overrides[$class] ?? null,
                'default_label' => $defaultLabel,
                'color' => $defaultColor,
                'type' => $type,
            ];
        });
    }

    /**
     * 获取状态机类的默认 label（不依赖 model 实例）
     */
    public static function getStateDefaultLabel(string $stateClass): string
    {
        if (!class_exists($stateClass)) {
            return class_basename($stateClass);
        }

        try {
            $reflection = new \ReflectionClass($stateClass);
            $instance = $reflection->newInstanceWithoutConstructor();
            if (method_exists($instance, 'label')) {
                return $instance->label();
            }
        } catch (\Throwable $e) {
            // 降级处理
        }

        return class_basename($stateClass);
    }

    /**
     * 获取状态机类的默认颜色（不依赖 model 实例）
     */
    public static function getStateDefaultColor(string $stateClass): string
    {
        if (!class_exists($stateClass)) {
            return '#6c757d';
        }

        try {
            $reflection = new \ReflectionClass($stateClass);
            $instance = $reflection->newInstanceWithoutConstructor();
            if (method_exists($instance, 'color')) {
                return $instance->color();
            }
        } catch (\Throwable $e) {
            // 降级处理
        }

        return '#6c757d';
    }

    /**
     * 获取某个状态类名对应的显示标签
     */
    public static function getLabel(string $stateClass): string
    {
        $override = self::where('state_class', $stateClass)->value('custom_label');
        if ($override) {
            return $override;
        }

        return self::getStateDefaultLabel($stateClass);
    }

    /**
     * 获取某个状态类名对应的颜色
     */
    public static function getColor(string $stateClass): string
    {
        return self::getStateDefaultColor($stateClass);
    }
}
