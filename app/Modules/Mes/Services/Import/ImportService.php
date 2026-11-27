<?php

declare(strict_types=1);

namespace App\Modules\Mes\Services\Import;

use App\Modules\Mes\Models\Bom;
use App\Modules\Mes\Models\Material;
use App\Modules\Mes\Models\Routing;
use App\Modules\Mes\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Excel 导入（M1-11~M1-14）：解析 → 校验 → 预览 → 确认入库。
 *
 * 为什么是两阶段：演示时最有说服力的一幕是"我把你现有的 Excel 丢进去，
 * 系统先把有问题的行标红让你改，确认后才入库"。一次性闷头导入反而看不出专业度。
 */
class ImportService
{
    public const TYPES = [
        'materials' => [
            'label' => '物料主数据',
            'columns' => ['物料编码', '名称', '规格', '单位', '类别', '安全库存', '现有库存', '标准成本'],
            'sample' => [
                ['MJ-RM-901', '铝型材 6063', '30×30×1000', 'M', '原料', '200', '800', '36.5'],
                ['MJ-FG-901', '演示成品 A', '标准款', 'PCS', '成品', '20', '35', '1280'],
            ],
        ],
        'boms' => [
            'label' => 'BOM 物料清单',
            'columns' => ['BOM编码', '产品编码', '版本', '组件编码', '单件用量', '损耗率', '行序'],
            'sample' => [
                ['BOM-FG-901', 'MJ-FG-901', 'V1', 'MJ-RM-901', '2', '0.02', '10'],
                ['BOM-FG-901', 'MJ-FG-901', 'V1', 'MJ-SM-001', '1', '0.01', '20'],
            ],
        ],
        'work-orders' => [
            'label' => '生产工单',
            'columns' => ['工单号', '产品编码', '数量', '计划开工', '计划完工', '优先级', '客户'],
            'sample' => [
                ['WO-2026-901', 'MJ-FG-001', '60', '2026-12-01', '2026-12-05', '5', '演示客户'],
            ],
        ],
    ];

    public function __construct(private readonly SheetReader $reader = new SheetReader())
    {
    }

    /**
     * @return array{type:string, rows:array<int, array>, ok:int, bad:int, total:int}
     */
    public function parse(string $type, string $path, string $extension): array
    {
        if (! isset(self::TYPES[$type])) {
            throw new RuntimeException('不支持的导入类型。');
        }

        $sheet = $this->reader->rows($path, $extension);
        if (count($sheet) < 2) {
            throw new RuntimeException('文件里只有表头，没有数据行。');
        }

        $header = array_shift($sheet);
        $map = $this->reader->headerMap($header, self::TYPES[$type]['columns']);

        $materials = Material::query()->get(['id', 'code', 'name', 'category'])->keyBy('code');
        $seen = [];
        $rows = [];

        foreach ($sheet as $i => $cells) {
            $values = $this->reader->pick($cells, $map);
            $line = $i + 2;   // Excel 里的真实行号（第 1 行是表头）

            [$errors, $notes] = match ($type) {
                'materials' => $this->validateMaterial($values, $materials),
                'boms' => $this->validateBom($values, $materials, $seen),
                'work-orders' => $this->validateWorkOrder($values, $materials),
            };

            $rows[] = [
                'line' => $line,
                'values' => $values,
                'errors' => $errors,
                'notes' => $notes,
            ];
        }

        $bad = count(array_filter($rows, fn (array $r) => $r['errors'] !== []));

        return [
            'type' => $type,
            'rows' => $rows,
            'total' => count($rows),
            'ok' => count($rows) - $bad,
            'bad' => $bad,
        ];
    }

    /**
     * 确认入库。只写校验通过的行，全量包在一个事务里。
     *
     * @param  array<int, array>  $rows
     * @return array{created:int, updated:int, failed:int, messages:array<int, string>}
     */
    public function commit(string $type, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $messages = [];

        $valid = array_filter($rows, fn (array $r) => ($r['errors'] ?? []) === []);

        DB::connection('mes')->transaction(function () use ($type, $valid, &$created, &$updated, &$failed, &$messages) {
            foreach ($valid as $row) {
                $v = $row['values'];
                try {
                    match ($type) {
                        'materials' => $this->commitMaterial($v, $created, $updated),
                        'boms' => $this->commitBom($v, $created, $updated),
                        'work-orders' => $this->commitWorkOrder($v, $created),
                    };
                } catch (\Throwable $e) {
                    $failed++;
                    $messages[] = "第 {$row['line']} 行写入失败：" . $e->getMessage();
                }
            }
        });

        return compact('created', 'updated', 'failed', 'messages');
    }

    // ---------------------------------------------------------------- 校验

    /** @return array{0: array<int,string>, 1: array<int,string>} */
    private function validateMaterial(array $v, $materials): array
    {
        $errors = [];
        $notes = [];

        $code = strtoupper(trim($v['物料编码']));
        if ($code === '') {
            $errors[] = '物料编码不能为空';
        } elseif (! preg_match('/^MJ-[A-Z]{2}-\d{3}$/', $code)) {
            $errors[] = '编码格式应为 MJ-XX-001（如 MJ-RM-901）';
        } elseif ($materials->has($code)) {
            $errors[] = "编码 {$code} 在系统里已存在";
        }

        if (trim($v['名称']) === '') {
            $errors[] = '名称不能为空';
        }

        $category = $this->normalizeCategory($v['类别']);
        if ($category === null) {
            $errors[] = '类别只能是 原料 / 半成品 / 成品';
        }

        foreach (['安全库存', '现有库存', '标准成本'] as $col) {
            $raw = trim($v[$col]);
            if ($raw !== '' && ! is_numeric($raw)) {
                $errors[] = "{$col} 必须是数字";
            } elseif ($raw !== '' && (float) $raw < 0) {
                $errors[] = "{$col} 不能是负数";
            }
        }

        if (trim($v['单位']) === '') {
            $notes[] = '单位为空，按 PCS 处理';
        }

        return [$errors, $notes];
    }

    /** @return array{0: array<int,string>, 1: array<int,string>} */
    private function validateBom(array $v, $materials, array &$seen): array
    {
        $errors = [];
        $notes = [];

        $bomCode = strtoupper(trim($v['BOM编码']));
        $productCode = strtoupper(trim($v['产品编码']));
        $componentCode = strtoupper(trim($v['组件编码']));

        if (! preg_match('/^BOM-[A-Z]{2,3}-\d{3}$/', $bomCode)) {
            $errors[] = 'BOM 编码格式应为 BOM-XX-001';
        }

        $product = $materials->get($productCode);
        if (! $product) {
            $errors[] = "产品编码 {$productCode} 不存在，请先导入物料";
        } elseif (! in_array($product->category, ['finished', 'semi'], true)) {
            $errors[] = '产品必须是成品或半成品';
        }

        if (! $materials->has($componentCode)) {
            $errors[] = "组件编码 {$componentCode} 不存在，请先导入物料";
        }

        $qty = trim($v['单件用量']);
        if (! is_numeric($qty) || (float) $qty <= 0) {
            $errors[] = '单件用量必须是大于 0 的数字';
        }

        $loss = $this->normalizeLoss($v['损耗率']);
        if ($loss === null) {
            $errors[] = '损耗率必须是 0 ~ 0.5 之间的数字（填 2 表示 2%）';
        }

        if ($errors === []) {
            $key = $bomCode . '|' . $componentCode;
            if (isset($seen[$key])) {
                $errors[] = "同一 BOM 里组件 {$componentCode} 重复（第 {$seen[$key]} 行已出现）";
            } else {
                $seen[$key] = trim($v['行序']) !== '' ? (int) $v['行序'] : 0;
            }
        }

        if (Bom::query()->where('code', $bomCode)->exists()) {
            $notes[] = 'BOM 已存在，这一行会追加或覆盖到现有清单';
        }

        return [$errors, $notes];
    }

    /** @return array{0: array<int,string>, 1: array<int,string>} */
    private function validateWorkOrder(array $v, $materials): array
    {
        $errors = [];
        $notes = [];

        $orderNo = strtoupper(trim($v['工单号']));
        if (! preg_match('/^WO-\d{4}-\d{3,4}$/', $orderNo)) {
            $errors[] = '工单号格式应为 WO-2026-001';
        } elseif (WorkOrder::query()->where('order_no', $orderNo)->exists()) {
            $errors[] = "工单号 {$orderNo} 已存在";
        }

        $productCode = strtoupper(trim($v['产品编码']));
        $product = $materials->get($productCode);
        if (! $product) {
            $errors[] = "产品编码 {$productCode} 不存在";
        } elseif (! in_array($product->category, ['finished', 'semi'], true)) {
            $errors[] = '工单产品必须是成品或半成品';
        }

        $qty = trim($v['数量']);
        if (! is_numeric($qty) || (float) $qty <= 0) {
            $errors[] = '数量必须是大于 0 的数字';
        }

        foreach (['计划开工', '计划完工'] as $col) {
            $raw = trim($v[$col]);
            if ($raw !== '' && $this->parseDate($raw) === null) {
                $errors[] = "{$col} 不是合法日期（建议写成 2026-12-01）";
            }
        }

        $prio = trim($v['优先级']);
        if ($prio !== '' && (! is_numeric($prio) || (int) $prio < 1 || (int) $prio > 9)) {
            $errors[] = '优先级填 1~9，1 最急';
        }

        $start = $this->parseDate($v['计划开工']);
        $end = $this->parseDate($v['计划完工']);
        if ($start && $end && $end < $start) {
            $errors[] = '计划完工不能早于计划开工';
        }

        return [$errors, $notes];
    }

    // ---------------------------------------------------------------- 入库

    private function commitMaterial(array $v, int &$created, int &$updated): void
    {
        Material::query()->create([
            'code' => strtoupper(trim($v['物料编码'])),
            'name' => trim($v['名称']),
            'spec' => trim($v['规格']) ?: null,
            'unit' => trim($v['单位']) ?: 'PCS',
            'category' => $this->normalizeCategory($v['类别']),
            'safety_stock' => (float) ($v['安全库存'] ?: 0),
            'stock_qty' => (float) ($v['现有库存'] ?: 0),
            'std_cost' => (float) ($v['标准成本'] ?: 0),
            'is_active' => true,
        ]);
        $created++;
    }

    private function commitBom(array $v, int &$created, int &$updated): void
    {
        $bomCode = strtoupper(trim($v['BOM编码']));
        $productCode = strtoupper(trim($v['产品编码']));
        $componentCode = strtoupper(trim($v['组件编码']));

        $bom = Bom::query()->firstOrCreate(
            ['code' => $bomCode],
            [
                'product_material_id' => Material::query()->where('code', $productCode)->value('id'),
                'version' => strtoupper(trim($v['版本'])) ?: 'V1',
                'status' => 'active',
                'remark' => '由 Excel 导入',
            ]
        );

        if ($bom->wasRecentlyCreated) {
            $created++;
        }

        $componentId = Material::query()->where('code', $componentCode)->value('id');
        $seq = trim($v['行序']) !== '' ? (int) $v['行序'] : ((int) $bom->lines()->max('seq') + 10);

        $bom->lines()->updateOrCreate(
            ['component_material_id' => $componentId],
            [
                'qty_per' => (float) $v['单件用量'],
                'loss_rate' => $this->normalizeLoss($v['损耗率']) ?? 0,
                'seq' => $seq,
            ]
        );
        $updated++;
    }

    private function commitWorkOrder(array $v, int &$created): void
    {
        $productId = Material::query()->where('code', strtoupper(trim($v['产品编码'])))->value('id');

        $bom = Bom::query()->where('product_material_id', $productId)->where('status', 'active')->first();
        $routing = Routing::query()->where('product_material_id', $productId)->where('status', 'active')->first();

        $order = WorkOrder::query()->create([
            'order_no' => strtoupper(trim($v['工单号'])),
            'product_material_id' => $productId,
            'bom_id' => $bom?->id,
            'routing_id' => $routing?->id,
            'plan_qty' => (float) $v['数量'],
            'status' => 'released',
            'plan_start' => $this->parseDate($v['计划开工']),
            'plan_end' => $this->parseDate($v['计划完工']),
            'priority' => (int) ($v['优先级'] ?: 5),
            'customer' => trim($v['客户']) ?: null,
            'remark' => '由 Excel 导入',
        ]);

        // 有工艺路线就跟着生成工序，工单进车间立刻能派活
        if ($routing) {
            foreach ($routing->operations as $op) {
                $order->operations()->create([
                    'op_no' => $op->op_no,
                    'op_name' => $op->op_name,
                    'work_center_id' => $op->work_center_id,
                    'plan_qty' => (float) $v['数量'],
                    'status' => 'pending',
                    'std_minutes' => $op->std_minutes,
                ]);
            }
        }

        $created++;
    }

    // ---------------------------------------------------------------- 小工具

    private function normalizeCategory(string $raw): ?string
    {
        $v = mb_strtolower(trim($raw));

        return match ($v) {
            'raw', '原料', '原材料' => 'raw',
            'semi', '半成品', '组件' => 'semi',
            'finished', '成品', '产成品' => 'finished',
            default => null,
        };
    }

    private function normalizeLoss(string $raw): ?float
    {
        $v = trim($raw);
        if ($v === '') {
            return 0.0;
        }
        if (str_ends_with($v, '%')) {
            $v = rtrim($v, '%');
        }
        if (! is_numeric($v)) {
            return null;
        }

        $num = (float) $v;
        if ($num > 1) {
            $num /= 100;
        }

        return ($num >= 0 && $num <= 0.5) ? round($num, 4) : null;
    }

    private function parseDate(string $raw): ?string
    {
        $v = trim($raw);
        if ($v === '') {
            return null;
        }

        // Excel 里日期常被存成序列号（45000 这种）
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 60000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->toDateString();
        }

        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
