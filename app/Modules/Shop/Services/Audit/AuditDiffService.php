<?php

namespace App\Modules\Shop\Services\Audit;

use App\Modules\Shop\Models\AuditFieldDiff;
use App\Modules\Shop\Models\AuditLog;
use Illuminate\Support\Collection;

/**
 * 审计字段级差异：抽取、落地（宽表）、回补与对比数据组装。
 *
 * 三种日志形态都要覆盖：
 * 1. 字段级日志（field 非空，old/new 为单值）→ 一条差异；
 * 2. 整对象快照日志（old/new 为关联数组，如历史主数据钩子）→ 逐字段拆分，标注新增/删除/修改；
 * 3. 无前后值（纯动作日志）→ 不产生差异。
 */
class AuditDiffService
{
    /** 不参与差异展示的技术字段。 */
    public const IGNORED = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'password', 'remember_token', 'email_verified_at',
    ];

    /** 常用字段中文标签，未收录的保持原字段名。 */
    public const FIELD_LABELS = [
        'code' => '编码',
        'name' => '名称',
        'barcode' => '条码',
        'list_price' => '零售价',
        'standard_cost' => '标准成本',
        'avg_cost' => '平均成本',
        'cost_method' => '成本计价方式',
        'traceability' => '追溯方式',
        'shelf_life_days' => '保质期天数',
        'category_id' => '商品类别',
        'unit_id' => '计量单位',
        'tax_id' => '税率',
        'is_active' => '启用状态',
        'description' => '备注说明',
        'image_path' => '商品主图',
        'status' => '状态',
        'qty' => '数量',
        'unit_price' => '单价',
        'discount_rate' => '折扣率',
        'subtotal' => '行小计',
        'tax_amount' => '税额',
        'phone' => '电话',
        'email' => '邮箱',
        'address' => '地址',
        'tax_no' => '税号',
        'contact_name' => '联系人',
        'membership_level' => '会员等级',
        'credit_limit' => '信用额度',
        'default_discount_rate' => '默认折扣率',
        'total_amount' => '金额合计',
        'paid_amount' => '已收/已付',
        'reference' => '外部参考',
        'note' => '备注',
        'reason' => '原因',
        'operator_id' => '操作人',
        'warehouse_id' => '仓库',
        'location_id' => '库位',
        'product_id' => '商品',
        'partner_id' => '往来单位',
        'entry_id' => '凭证',
        'due_date' => '到期日',
        'invoice_date' => '开票日期',
        'bill_date' => '账单日期',
        'amount' => '金额',
        'price' => '价格',
        'rate' => '比率',
        'document_no' => '单据号',
    ];

    /**
     * 抽取字段差异。
     *
     * @return array<int, array{field: string, label: string, old: mixed, new: mixed, old_text: string, new_text: string, type: string}>
     */
    public function extract(AuditLog $log): array
    {
        $oldMap = $this->asMap($log->old_value);
        $newMap = $this->asMap($log->new_value);

        if ($oldMap !== null || $newMap !== null) {
            return $this->diffMaps($oldMap ?? [], $newMap ?? []);
        }

        $old = $this->unwrapScalar($log->old_value);
        $new = $this->unwrapScalar($log->new_value);
        $oldText = $this->format($old);
        $newText = $this->format($new);

        if ($oldText === $newText) {
            return [];
        }

        return [$this->row($log->field ?: 'value', $old, $new, $oldText, $newText, AuditFieldDiff::TYPE_CHANGED)];
    }

    /**
     * 计算并写入派生表（重复调用结果一致：先清后写）。
     */
    public function store(AuditLog $log): int
    {
        AuditFieldDiff::where('log_id', $log->getKey())->delete();

        $rows = $this->extract($log);
        $now = now();

        foreach ($rows as $row) {
            AuditFieldDiff::create([
                'log_id' => $log->getKey(),
                'model' => $log->model,
                'record_id' => $log->record_id,
                'action' => $log->action,
                'field' => $row['field'],
                'field_label' => $row['label'],
                'old_text' => $row['old_text'],
                'new_text' => $row['new_text'],
                'change_type' => $row['type'],
                'created_at' => $now,
            ]);
        }

        return count($rows);
    }

    /**
     * 取某条日志的差异：优先读派生表，缺失时即时计算（不写库，避免读路径产生写入）。
     *
     * @return array<int, array{field: string, label: string, old: mixed, new: mixed, old_text: string, new_text: string, type: string}>
     */
    public function forLog(AuditLog $log): array
    {
        $stored = $log->fieldDiffs;

        if ($stored->isNotEmpty()) {
            return $stored->map(fn (AuditFieldDiff $diff) => [
                'field' => $diff->field,
                'label' => $diff->field_label ?: $this->label($diff->field),
                'old' => null,
                'new' => null,
                'old_text' => (string) $diff->old_text,
                'new_text' => (string) $diff->new_text,
                'type' => $diff->change_type,
            ])->all();
        }

        return $this->extract($log);
    }

    /**
     * 同一次操作产生的多条日志（一次更新多个字段会拆成多行）。
     *
     * @return Collection<int, AuditLog>
     */
    public function changeSet(AuditLog $log): Collection
    {
        if (! $log->acted_at) {
            return collect([$log]);
        }

        $window = [$log->acted_at->copy()->subSeconds(2), $log->acted_at->copy()->addSeconds(2)];

        return AuditLog::query()
            ->with(['operator', 'fieldDiffs'])
            ->where('model', $log->model)
            ->where('record_id', $log->record_id)
            ->where('action', $log->action)
            ->when(
                $log->operator_id === null,
                fn ($q) => $q->whereNull('operator_id'),
                fn ($q) => $q->where('operator_id', $log->operator_id)
            )
            ->whereBetween('acted_at', $window)
            ->orderBy('id')
            ->get();
    }

    /**
     * 回补派生表：扫描尚无差异记录的日志，逐条计算写入。
     *
     * @return array{logs: int, rows: int}
     */
    public function backfill(int $chunkSize = 500): array
    {
        $logs = 0;
        $rows = 0;

        AuditLog::query()
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('audit_field_diffs')
                    ->whereColumn('audit_field_diffs.log_id', 'audit_logs.id');
            })
            ->orderBy('id')
            ->chunk($chunkSize, function ($chunk) use (&$logs, &$rows) {
                foreach ($chunk as $log) {
                    /** @var AuditLog $log */
                    $rows += $this->store($log);
                    $logs++;
                }
            });

        return ['logs' => $logs, 'rows' => $rows];
    }

    /**
     * 值可读化：布尔转是/否、空值标注、数组转 JSON、字符串去空白。
     */
    public function format(mixed $value): string
    {
        if ($value === null) {
            return '（空）';
        }
        if (is_bool($value)) {
            return $value ? '是' : '否';
        }
        if (is_array($value)) {
            if (array_key_exists('value', $value) && count($value) === 1) {
                return $this->format($value['value']);
            }

            $json = json_encode($value, JSON_UNESCAPED_UNICODE);

            return $json === false ? '[]' : $json;
        }
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? '（空）' : $trimmed;
        }

        return (string) $value;
    }

    public function label(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            AuditFieldDiff::TYPE_ADDED => '新增',
            AuditFieldDiff::TYPE_REMOVED => '删除',
            default => '修改',
        };
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<int, array{field: string, label: string, old: mixed, new: mixed, old_text: string, new_text: string, type: string}>
     */
    private function diffMaps(array $old, array $new): array
    {
        $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
        $rows = [];

        foreach ($fields as $field) {
            if (in_array($field, self::IGNORED, true)) {
                continue;
            }

            $hasOld = array_key_exists($field, $old);
            $hasNew = array_key_exists($field, $new);
            $oldValue = $hasOld ? $old[$field] : null;
            $newValue = $hasNew ? $new[$field] : null;
            $oldText = $hasOld ? $this->format($oldValue) : '（空）';
            $newText = $hasNew ? $this->format($newValue) : '（空）';

            if ($oldText === $newText) {
                continue;
            }

            $type = match (true) {
                ! $hasOld && $hasNew => AuditFieldDiff::TYPE_ADDED,
                $hasOld && ! $hasNew => AuditFieldDiff::TYPE_REMOVED,
                default => AuditFieldDiff::TYPE_CHANGED,
            };

            $rows[] = [
                'field' => $field,
                'label' => $this->label($field),
                'old' => $oldValue,
                'new' => $newValue,
                'old_text' => $oldText,
                'new_text' => $newText,
                'type' => $type,
            ];
        }

        return $rows;
    }

    /**
     * 关联数组才视为「整对象快照」；列表（如明细行集合）与 ['value'=>x] 包装不算。
     */
    private function asMap(mixed $value): ?array
    {
        if (! is_array($value) || $value === [] || array_is_list($value)) {
            return null;
        }
        if (count($value) === 1 && array_key_exists('value', $value)) {
            return null;
        }

        return $value;
    }

    private function unwrapScalar(mixed $value): mixed
    {
        if (is_array($value) && count($value) === 1 && array_key_exists('value', $value)) {
            return $value['value'];
        }

        return $value;
    }

    /**
     * @return array{field: string, label: string, old: mixed, new: mixed, old_text: string, new_text: string, type: string}
     */
    private function row(string $field, mixed $old, mixed $new, string $oldText, string $newText, string $type): array
    {
        return [
            'field' => $field,
            'label' => $this->label($field),
            'old' => $old,
            'new' => $new,
            'old_text' => $oldText,
            'new_text' => $newText,
            'type' => $type,
        ];
    }
}
