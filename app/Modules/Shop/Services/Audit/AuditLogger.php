<?php

namespace App\Modules\Shop\Services\Audit;

use App\Modules\Shop\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 审计写入器。
 *
 * 与业务在同一数据库事务内写入：业务回滚则日志回滚；不在此处另开事务。
 * 上下文（操作人 / IP / UA / 会话）由 AuditContext 中间件注入；CLI / 系统任务回退为空。
 */
class AuditLogger
{
    /**
     * 写入一条审计日志。
     *
     * @param  array{
     *   module: string, action: string, model?: ?string, record_id?: ?int|string,
     *   document_no?: ?string, field?: ?string, old_value?: mixed, new_value?: mixed,
     *   source?: string, context?: ?array, message?: ?string, operator_id?: ?int
     * }  $data
     */
    public function record(array $data): AuditLog
    {
        $context = $this->context();

        $payload = [
            'operator_id' => $data['operator_id'] ?? $context->user_id ?? null,
            'acted_at' => Carbon::now(),
            'module' => $data['module'] ?? 'system',
            'model' => $data['model'] ?? null,
            'record_id' => $data['record_id'] ?? null,
            'document_no' => $data['document_no'] ?? null,
            'action' => $data['action'] ?? AuditLog::ACTION_UPDATE,
            'field' => $data['field'] ?? null,
            'old_value' => $this->normalize($data['old_value'] ?? null),
            'new_value' => $this->normalize($data['new_value'] ?? null),
            'source' => $data['source'] ?? 'web',
            'ip' => $context->ip ?? null,
            'user_agent' => Str::limit($context->user_agent ?? '', 255, ''),
            'session_id' => $context->session_id ?? null,
            'context' => $this->normalize($data['context'] ?? null),
            'message' => $data['message'] ?? null,
        ];

        return AuditLog::create($payload);
    }

    /**
     * 记录某个 Eloquent 模型的变更（字段级 diff）。
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $diff  字段 => [旧值, 新值]
     */
    public function logModel(
        object $model,
        string $action,
        array $diff = [],
        ?string $documentNo = null,
        array $extraContext = [],
        ?string $module = null
    ): void {
        $modelClass = get_class($model);
        $recordId = $model->getKey();
        $module ??= $extraContext['module'] ?? null;

        if ($diff === []) {
            $this->record([
                'module' => $module,
                'model' => $modelClass,
                'record_id' => $recordId,
                'document_no' => $documentNo,
                'action' => $action,
                'context' => $extraContext,
            ]);

            return;
        }

        foreach ($diff as $field => [$old, $new]) {
            $this->record([
                'module' => $module,
                'model' => $modelClass,
                'record_id' => $recordId,
                'document_no' => $documentNo,
                'action' => $action,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'context' => $extraContext,
            ]);
        }
    }

    /**
     * 获取当前请求注入的审计上下文。
     */
    private function context(): object
    {
        return app()->bound('audit.context') ? app('audit.context') : (object) [];
    }

    /**
     * JSON 字段归一化：标量包成数组，对象转数组；避免无法编码的值。
     */
    private function normalize(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return ['value' => $value];
    }
}
