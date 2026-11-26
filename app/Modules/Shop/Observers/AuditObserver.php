<?php

namespace App\Modules\Shop\Observers;

use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * 主数据审计观察者基类。
 *
 * 关键模型 created / updated / deleted 自动采集字段级前后值，
 * 交 AuditLogger 写入不可删审计日志；业务事务回滚则日志一并回滚。
 */
abstract class AuditObserver
{
    /** 审计模块标识，子类覆盖。 */
    protected string $module = 'master';

    /** 不纳入 diff 的字段。 */
    protected array $ignored = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct(protected AuditLogger $logger)
    {
    }

    public function created(Model $model): void
    {
        $diff = [];
        foreach ($this->attributes($model) as $field => $value) {
            $diff[$field] = [null, $value];
        }

        $this->logger->logModel($model, AuditLog::ACTION_CREATE, $diff, null, ['module' => $this->module]);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $diff = [];

        foreach ($changes as $field => $new) {
            if (in_array($field, $this->ignored, true)) {
                continue;
            }
            $diff[$field] = [$model->getOriginal($field), $new];
        }

        if ($diff !== []) {
            $this->logger->logModel($model, AuditLog::ACTION_UPDATE, $diff, null, ['module' => $this->module]);
        }
    }

    public function deleted(Model $model): void
    {
        $diff = [];
        foreach ($this->attributes($model) as $field => $value) {
            $diff[$field] = [$value, null];
        }

        $this->logger->logModel($model, AuditLog::ACTION_DELETE, $diff, null, ['module' => $this->module]);
    }

    /**
     * 取模型业务属性（排除忽略字段与临时属性）。
     *
     * @return array<string, mixed>
     */
    private function attributes(Model $model): array
    {
        $attributes = $model->getAttributes();
        $result = [];

        foreach ($attributes as $field => $value) {
            if (! in_array($field, $this->ignored, true)) {
                $result[$field] = $value;
            }
        }

        return $result;
    }
}
