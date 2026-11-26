<?php

namespace App\Modules\Shop\Services\Exchange;

use App\Modules\Shop\Exceptions\ImportException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Customer;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductCategory;
use App\Modules\Shop\Models\Supplier;
use App\Modules\Shop\Models\Tax;
use App\Modules\Shop\Models\Unit;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * 商品 / 客户 / 供应商 三类主数据的 CSV 模板、导出与导入。
 *
 * 设计约束：
 * - 表头支持中文（模板列名）与英文字段名双写法，缺必需列直接拒绝整份文件；
 * - 导入为「全量校验后再落库」：任一行有错则整份不导入，并把错误按真实行号回显，便于定位；
 * - 导出统一 UTF-8 + BOM，Excel 双击打开中文不乱码；以 = + - @ 开头的文本加前缀，避免公式注入；
 * - 导出写 ACTION_EXPORT 审计，导入写 ACTION_CREATE 汇总审计。
 */
class MasterDataExchange
{
    public const BOM = "\xEF\xBB\xBF";

    /** 类型 => [中文名, 列表路由, 中文表头, 必需字段] */
    private const SCHEMA = [
        'products' => [
            'label' => '商品',
            'index' => 'products.index',
            'columns' => [
                'code' => '商品编码',
                'barcode' => '条码',
                'name' => '商品名称',
                'category' => '类别',
                'unit' => '计量单位',
                'tax' => '税率代码',
                'cost_method' => '成本计价方式',
                'standard_cost' => '标准成本',
                'list_price' => '零售价',
                'traceability' => '追溯方式',
                'shelf_life_days' => '保质期天数',
                'description' => '备注说明',
                'is_active' => '是否启用',
            ],
            'required' => ['code', 'name'],
            'sample' => ['P001', '6901234567890', '示例矿泉水', '饮料', '瓶', 'VAT13', 'standard', '1.20', '2.00', 'none', '365', '示例备注', '1'],
        ],
        'customers' => [
            'label' => '客户',
            'index' => 'customers.index',
            'columns' => [
                'code' => '客户编码',
                'name' => '客户名称',
                'contact_name' => '联系人',
                'phone' => '电话',
                'email' => '邮箱',
                'address' => '地址',
                'tax_no' => '税号',
                'membership_level' => '会员等级',
                'credit_limit' => '信用额度',
                'default_discount_rate' => '默认折扣率',
                'is_active' => '是否启用',
            ],
            'required' => ['code', 'name'],
            'sample' => ['C001', '示例客户', '张三', '13800000000', 'demo@example.com', '深圳市南山区示例路 1 号', '91440300XXXXXXXX', 'normal', '1000', '5', '1'],
        ],
        'suppliers' => [
            'label' => '供应商',
            'index' => 'suppliers.index',
            'columns' => [
                'code' => '供应商编码',
                'name' => '供应商名称',
                'contact_name' => '联系人',
                'phone' => '电话',
                'email' => '邮箱',
                'address' => '地址',
                'tax_no' => '税号',
                'is_active' => '是否启用',
            ],
            'required' => ['code', 'name'],
            'sample' => ['S001', '示例供应商', '李四', '07550000000', 'supplier@example.com', '深圳市福田区示例路 2 号', '91440300YYYYYYYY', '1'],
        ],
    ];

    public const TYPES = ['products', 'customers', 'suppliers'];

    public function __construct(
        private readonly CsvReader $reader,
        private readonly AuditLogger $audit,
    ) {
    }

    public static function supports(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /**
     * @return array{label: string, index: string, columns: array<string, string>}
     */
    public function meta(string $type): array
    {
        $schema = $this->schema($type);

        return ['label' => $schema['label'], 'index' => $schema['index'], 'columns' => $schema['columns']];
    }

    /**
     * 导出/模板写入：输出 BOM + 表头 + 数据行。
     *
     * @param  resource  $handle
     * @param  array<int, array<int, mixed>>|null  $rows  null 表示输出模板样例行
     */
    public function write($handle, string $type, ?array $rows = null): int
    {
        $schema = $this->schema($type);
        $rows ??= [$schema['sample']];

        fwrite($handle, self::BOM);
        fputcsv($handle, array_values($schema['columns']), ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value) => $this->escape((string) $value), $row), ',', '"', '\\');
        }

        return count($rows);
    }

    /**
     * 组装某类主数据的导出数据（表头+中文表头顺序一致）。
     *
     * @return array<int, array<int, mixed>>
     */
    public function rows(string $type): array
    {
        return match ($type) {
            'products' => $this->productRows(),
            'customers' => $this->customerRows(),
            'suppliers' => $this->supplierRows(),
            default => throw new ImportException('不支持的数据类型'),
        };
    }

    public function templateName(string $type): string
    {
        return $this->schema($type)['label'].'导入模板.csv';
    }

    public function exportName(string $type): string
    {
        return $this->schema($type)['label'].'数据_'.now()->format('Ymd_Hi').'.csv';
    }

    /**
     * 导入：先整体校验，全部通过才落库。
     */
    public function import(string $type, UploadedFile $file): ImportResult
    {
        $schema = $this->schema($type);
        $parsed = $this->reader->read($file);
        $index = $this->mapHeader($type, $parsed['header']);

        $errors = [];
        $records = [];
        $seenCodes = [];

        foreach ($parsed['rows'] as $row) {
            $record = $this->extract($index, $row['cells']);
            $rowErrors = [];
            $attributes = match ($type) {
                'products' => $this->normalizeProduct($record, $row['line'], $seenCodes, $rowErrors),
                'customers' => $this->normalizeCustomer($record, $row['line'], $seenCodes, $rowErrors),
                default => $this->normalizeSupplier($record, $row['line'], $seenCodes, $rowErrors),
            };

            if ($rowErrors !== []) {
                $errors[] = ['line' => $row['line'], 'message' => implode('；', $rowErrors)];

                continue;
            }

            $records[] = $attributes;
        }

        if ($errors !== []) {
            return new ImportResult(0, $errors);
        }

        DB::transaction(function () use ($type, $records) {
            foreach ($records as $attributes) {
                match ($type) {
                    'products' => Product::create($attributes),
                    'customers' => Customer::create($attributes),
                    default => Supplier::create($attributes),
                };
            }

            $this->audit->record([
                'module' => 'exchange',
                'action' => AuditLog::ACTION_CREATE,
                'model' => match ($type) {
                    'products' => Product::class,
                    'customers' => Customer::class,
                    default => Supplier::class,
                },
                'message' => 'CSV 导入'.$this->schema($type)['label'].' '.count($records).' 条',
                'context' => ['type' => $type, 'imported' => count($records)],
            ]);
        });

        return new ImportResult(count($records));
    }

    /**
     * 导出审计（在输出前一并写入，避免流式中断导致无痕迹）。
     */
    public function auditExport(string $type, int $rowCount): void
    {
        $this->audit->record([
            'module' => 'exchange',
            'action' => AuditLog::ACTION_EXPORT,
            'message' => '导出'.$this->schema($type)['label'].'数据 CSV（'.$rowCount.' 行）',
            'context' => ['type' => $type, 'rows' => $rowCount],
        ]);
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function mapHeader(string $type, array $header): array
    {
        $schema = $this->schema($type);
        $index = [];

        foreach ($header as $position => $cell) {
            $cell = trim($cell);
            if ($cell === '') {
                continue;
            }

            foreach ($schema['columns'] as $key => $label) {
                if ($cell === $label || mb_strtolower($cell) === $key) {
                    $index[$key] = $position;
                    break;
                }
            }
        }

        $missing = [];
        foreach ($schema['required'] as $key) {
            if (! array_key_exists($key, $index)) {
                $missing[] = $schema['columns'][$key];
            }
        }

        if ($missing !== []) {
            throw new ImportException('缺少必需列：'.implode('、', $missing).'，请使用下载的模板');
        }

        return $index;
    }

    /**
     * @param  array<string, int>  $index
     * @param  array<int, string>  $cells
     * @return array<string, string>
     */
    private function extract(array $index, array $cells): array
    {
        $record = [];
        foreach ($index as $key => $position) {
            $record[$key] = $cells[$position] ?? '';
        }

        return $record;
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<string, int>  $seenCodes
     * @param  array<int, string>  $errors
     */
    private function normalizeProduct(array $record, int $line, array &$seenCodes, array &$errors): array
    {
        $code = $this->text($record, 'code', 50, '商品编码', $errors, true);
        if ($code !== '') {
            $this->checkCode($code, $line, '商品编码', Product::class, $seenCodes, $errors);
        }

        $barcode = $this->text($record, 'barcode', 50, '条码', $errors);
        if ($barcode !== '' && Product::where('barcode', $barcode)->exists()) {
            $errors[] = "条码 {$barcode} 已被其他商品使用";
        }

        $name = $this->text($record, 'name', 100, '商品名称', $errors, true);

        $categoryId = null;
        if ($value = $this->text($record, 'category', 100, '类别', $errors)) {
            $category = ProductCategory::where('name', $value)->orWhere('code', $value)->first();
            $category ? $categoryId = $category->id : $errors[] = "类别「{$value}」不存在，请先在类别管理中添加";
        }

        $unitId = null;
        if ($value = $this->text($record, 'unit', 50, '计量单位', $errors)) {
            $unit = Unit::where('code', $value)->orWhere('name', $value)->first();
            $unit ? $unitId = $unit->id : $errors[] = "计量单位「{$value}」不存在，请先在单位管理中添加";
        }

        $taxId = null;
        if ($value = $this->text($record, 'tax', 50, '税率代码', $errors)) {
            $tax = Tax::where('code', $value)->first();
            $tax ? $taxId = $tax->id : $errors[] = "税率代码「{$value}」不存在";
        }

        $methodMap = ['standard' => 'standard', 'avco' => 'avco', 'fifo' => 'fifo', '标准成本' => 'standard', '移动加权' => 'avco', '先进先出' => 'fifo'];
        $methodRaw = $this->text($record, 'cost_method', 20, '成本计价方式', $errors);
        $costMethod = $methodRaw === '' ? 'standard' : ($methodMap[mb_strtolower($methodRaw)] ?? null);
        if ($costMethod === null) {
            $errors[] = "成本计价方式「{$methodRaw}」不合法，可选：标准成本 / 移动加权 / 先进先出";
        }

        $standardCost = $this->decimal($record, 'standard_cost', '标准成本', $errors, 0);
        $listPrice = $this->decimal($record, 'list_price', '零售价', $errors, 0);

        $traceMap = ['none' => 'none', '不追踪' => 'none', 'lot' => 'lot', '批次' => 'lot', '序列号' => 'serial', 'serial' => 'serial'];
        $traceRaw = $this->text($record, 'traceability', 20, '追溯方式', $errors);
        $trace = $traceRaw === '' ? 'none' : ($traceMap[mb_strtolower($traceRaw)] ?? null);
        if ($trace === null) {
            $errors[] = "追溯方式「{$traceRaw}」不合法，可选：不追踪 / 批次 / 序列号";
        }

        $shelfLife = null;
        if ($value = $this->text($record, 'shelf_life_days', 10, '保质期天数', $errors)) {
            if (! ctype_digit($value)) {
                $errors[] = '保质期天数必须是非负整数';
            } else {
                $shelfLife = (int) $value;
            }
        }

        $description = $this->text($record, 'description', 1000, '备注说明', $errors);

        return [
            'code' => $code,
            'barcode' => $barcode === '' ? null : $barcode,
            'name' => $name,
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'tax_id' => $taxId,
            'cost_method' => $costMethod ?? 'standard',
            'standard_cost' => $standardCost,
            'list_price' => $listPrice,
            'traceability' => $trace ?? 'none',
            'shelf_life_days' => $shelfLife,
            'description' => $description === '' ? null : $description,
            'is_active' => $this->bool($this->text($record, 'is_active', 10, '是否启用', $errors)),
        ];
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<string, int>  $seenCodes
     * @param  array<int, string>  $errors
     */
    private function normalizeCustomer(array $record, int $line, array &$seenCodes, array &$errors): array
    {
        $code = $this->text($record, 'code', 20, '客户编码', $errors, true);
        if ($code !== '') {
            $this->checkCode($code, $line, '客户编码', Customer::class, $seenCodes, $errors);
        }

        $name = $this->text($record, 'name', 100, '客户名称', $errors, true);

        $levelMap = ['normal' => 'normal', '普通' => 'normal', 'silver' => 'silver', '银卡' => 'silver', 'gold' => 'gold', '金卡' => 'gold'];
        $levelRaw = $this->text($record, 'membership_level', 20, '会员等级', $errors);
        $level = $levelRaw === '' ? 'normal' : ($levelMap[mb_strtolower($levelRaw)] ?? null);
        if ($level === null) {
            $errors[] = "会员等级「{$levelRaw}」不合法，可选：普通 / 银卡 / 金卡";
        }

        $creditLimit = $this->decimal($record, 'credit_limit', '信用额度', $errors, 0);
        $discount = $this->decimal($record, 'default_discount_rate', '默认折扣率', $errors, 0);
        if ($discount < 0 || $discount > 100) {
            $errors[] = '默认折扣率需在 0–100 之间（百分数）';
        }

        return [
            'code' => $code,
            'name' => $name,
            'contact_name' => $this->nullable($this->text($record, 'contact_name', 50, '联系人', $errors)),
            'phone' => $this->nullable($this->text($record, 'phone', 30, '电话', $errors)),
            'email' => $this->email($record, $errors),
            'address' => $this->nullable($this->text($record, 'address', 255, '地址', $errors)),
            'tax_no' => $this->nullable($this->text($record, 'tax_no', 30, '税号', $errors)),
            'membership_level' => $level ?? 'normal',
            'credit_limit' => $creditLimit,
            'default_discount_rate' => $discount,
            'is_active' => $this->bool($this->text($record, 'is_active', 10, '是否启用', $errors)),
        ];
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<string, int>  $seenCodes
     * @param  array<int, string>  $errors
     */
    private function normalizeSupplier(array $record, int $line, array &$seenCodes, array &$errors): array
    {
        $code = $this->text($record, 'code', 20, '供应商编码', $errors, true);
        if ($code !== '') {
            $this->checkCode($code, $line, '供应商编码', Supplier::class, $seenCodes, $errors);
        }

        return [
            'code' => $code,
            'name' => $this->text($record, 'name', 100, '供应商名称', $errors, true),
            'contact_name' => $this->nullable($this->text($record, 'contact_name', 50, '联系人', $errors)),
            'phone' => $this->nullable($this->text($record, 'phone', 30, '电话', $errors)),
            'email' => $this->email($record, $errors),
            'address' => $this->nullable($this->text($record, 'address', 255, '地址', $errors)),
            'tax_no' => $this->nullable($this->text($record, 'tax_no', 30, '税号', $errors)),
            'is_active' => $this->bool($this->text($record, 'is_active', 10, '是否启用', $errors)),
        ];
    }

    /**
     * 编码唯一性：先查库，再查同文件内重复（并指出首次出现的行号）。
     *
     * @param  array<string, int>  $seenCodes
     * @param  array<int, string>  $errors
     */
    private function checkCode(string $code, int $line, string $label, string $model, array &$seenCodes, array &$errors): void
    {
        if ($model::where('code', $code)->exists()) {
            $errors[] = "{$label} {$code} 已存在";

            return;
        }

        if (isset($seenCodes[$code])) {
            $errors[] = "{$label} {$code} 与第 {$seenCodes[$code]} 行重复";

            return;
        }

        $seenCodes[$code] = $line;
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<int, string>  $errors
     */
    private function text(array $record, string $key, int $maxLength, string $label, array &$errors, bool $required = false): string
    {
        $value = trim((string) ($record[$key] ?? ''));

        if ($value === '') {
            if ($required) {
                $errors[] = "{$label}不能为空";
            }

            return '';
        }

        if (mb_strlen($value) > $maxLength) {
            $errors[] = "{$label}不能超过 {$maxLength} 个字符";
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<int, string>  $errors
     */
    private function decimal(array $record, string $key, string $label, array &$errors, float $default = 0.0): float
    {
        $value = trim((string) ($record[$key] ?? ''));
        if ($value === '') {
            return $default;
        }

        $normalized = str_replace(['￥', '¥', ' ', ','], '', $value);
        if (! is_numeric($normalized)) {
            $errors[] = "{$label}「{$value}」不是有效数字";

            return $default;
        }

        $number = (float) $normalized;
        if ($number < 0) {
            $errors[] = "{$label}不能小于 0";
        }

        return max(0, $number);
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<int, string>  $errors
     */
    private function email(array $record, array &$errors): ?string
    {
        $value = $this->text($record, 'email', 100, '邮箱', $errors);
        if ($value === '') {
            return null;
        }
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "邮箱「{$value}」格式不正确";

            return null;
        }

        return $value;
    }

    private function bool(string $value, bool $default = true): bool
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'true', 'yes', 'y', '是', '启用'], true);
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * 防公式注入：以 = + - @ 开头（或含前导制表/回车）的文本加单引号前缀。
     */
    private function escape(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function productRows(): array
    {
        return Product::with(['category', 'unit', 'tax'])->orderBy('code')->get()->map(fn (Product $product) => [
            $product->code,
            (string) $product->barcode,
            $product->name,
            $product->category?->name ?? '',
            $product->unit?->code ?? '',
            $product->tax?->code ?? '',
            $product->cost_method,
            $this->number($product->standard_cost),
            $this->number($product->list_price),
            $product->traceability,
            $product->shelf_life_days === null ? '' : (string) $product->shelf_life_days,
            (string) $product->description,
            $product->is_active ? '1' : '0',
        ])->all();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function customerRows(): array
    {
        return Customer::orderBy('code')->get()->map(fn (Customer $customer) => [
            $customer->code,
            $customer->name,
            (string) $customer->contact_name,
            (string) $customer->phone,
            (string) $customer->email,
            (string) $customer->address,
            (string) $customer->tax_no,
            $customer->membership_level,
            $this->number($customer->credit_limit),
            $this->number($customer->default_discount_rate),
            $customer->is_active ? '1' : '0',
        ])->all();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function supplierRows(): array
    {
        return Supplier::orderBy('code')->get()->map(fn (Supplier $supplier) => [
            $supplier->code,
            $supplier->name,
            (string) $supplier->contact_name,
            (string) $supplier->phone,
            (string) $supplier->email,
            (string) $supplier->address,
            (string) $supplier->tax_no,
            $supplier->is_active ? '1' : '0',
        ])->all();
    }

    private function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * @return array{label: string, index: string, columns: array<string, string>, required: array<int, string>, sample: array<int, string>}
     */
    private function schema(string $type): array
    {
        if (! self::supports($type)) {
            throw new ImportException('不支持的数据类型');
        }

        return self::SCHEMA[$type];
    }
}
