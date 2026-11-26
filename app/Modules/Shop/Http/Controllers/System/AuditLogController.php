<?php

namespace App\Modules\Shop\Http\Controllers\System;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\User;
use App\Modules\Shop\Services\Audit\AuditDiffService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 审计日志查询：日志为只增不改不删的不可变记录，本页面仅展示与过滤。
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly AuditDiffService $diffs)
    {
    }

    /** 模块中文标签。 */
    public const MODULE_LABELS = [
        'pos' => 'POS 收银',
        'master' => '基础资料',
        'warehouse' => '仓储',
        'purchase' => '采购',
        'sales' => '销售',
        'accounting' => '财务',
        'system' => '系统',
    ];

    /** 动作中文标签。 */
    public const ACTION_LABELS = [
        AuditLog::ACTION_CREATE => '新建',
        AuditLog::ACTION_UPDATE => '更新',
        AuditLog::ACTION_DELETE => '删除',
        AuditLog::ACTION_REFUND => '退款',
        AuditLog::ACTION_PRICE_CHANGE => '改价',
        AuditLog::ACTION_DISCOUNT => '折扣',
        AuditLog::ACTION_STOCK_MOVE => '库存变动',
        AuditLog::ACTION_APPROVE => '审批',
        AuditLog::ACTION_LOGIN => '登录',
        AuditLog::ACTION_LOGOUT => '登出',
        AuditLog::ACTION_EXPORT => '导出',
    ];

    /**
     * 旧值/新值可读化：标量直出，单值数组取 value 键，其余 JSON 展开。
     */
    public static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_array($value)) {
            if (array_key_exists('value', $value) && count($value) === 1) {
                return (string) $value['value'];
            }
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with(['operator', 'fieldDiffs'])
            ->when($request->filled('operator_id'), fn ($q) => $q->where('operator_id', $request->input('operator_id')))
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->input('module')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('document_no'), fn ($q) => $q->where('document_no', 'like', "%{$request->input('document_no')}%"))
            ->when($request->filled('keyword'), fn ($q) => $q->where('message', 'like', "%{$request->input('keyword')}%"))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('acted_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('acted_at', '<=', $request->input('date_to')))
            ->orderByDesc('acted_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $operators = User::orderBy('name')->get(['id', 'name']);

        // 每条日志的字段差异摘要（优先读宽表，缺失则即时计算），用于列表高亮与「多字段」提示
        $diffSummaries = [];
        foreach ($logs as $log) {
            $rows = $this->diffs->forLog($log);
            $diffSummaries[$log->id] = [
                'count' => count($rows),
                'rows' => $rows,
                'fallback_old' => self::formatValue($log->old_value),
                'fallback_new' => self::formatValue($log->new_value),
            ];
        }

        return view('shop::system.audit-logs.index', [
            'logs' => $logs,
            'operators' => $operators,
            'moduleLabels' => self::MODULE_LABELS,
            'actionLabels' => self::ACTION_LABELS,
            'diffSummaries' => $diffSummaries,
        ]);
    }

    /**
     * 单次变更的字段级对比：把同一次操作拆出的多条日志合并成一张对比表。
     */
    public function show(AuditLog $auditLog): View
    {
        $log = $auditLog;
        $log->load(['operator', 'fieldDiffs']);

        $changeSet = $this->diffs->changeSet($log);

        $entries = [];
        foreach ($changeSet as $item) {
            foreach ($this->diffs->forLog($item) as $row) {
                $entries[] = $row + ['log' => $item];
            }
        }

        return view('shop::system.audit-logs.show', [
            'log' => $log,
            'entries' => $entries,
            'changeSet' => $changeSet,
            'moduleLabels' => self::MODULE_LABELS,
            'actionLabels' => self::ACTION_LABELS,
            'typeLabels' => [
                \App\Modules\Shop\Models\AuditFieldDiff::TYPE_ADDED => '新增',
                \App\Modules\Shop\Models\AuditFieldDiff::TYPE_CHANGED => '修改',
                \App\Modules\Shop\Models\AuditFieldDiff::TYPE_REMOVED => '删除',
            ],
        ]);
    }
}
