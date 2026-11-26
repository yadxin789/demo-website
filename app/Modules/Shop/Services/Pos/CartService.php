<?php

namespace App\Modules\Shop\Services\Pos;

use App\Modules\Shop\Exceptions\PosException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Customer;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;

/**
 * POS 购物车服务：商品检索（条码/编码精确、名称模糊）、行快照构建、
 * 重复扫码合并累加、车行改数量/改价/行折扣/删行与合计计算。
 * 购物车状态在前端维护，本服务提供可测试的取数、校验与留痕规则；
 * 挂单序列化在 P1-508，结账落库在 P1-509/510。
 */
class CartService
{
    public const TRACE_NONE = 'none';
    public const TRACE_LOT = 'lot';
    public const TRACE_SERIAL = 'serial';

    public function __construct(protected AuditLogger $logger)
    {
    }

    /**
     * 检索在售商品：条码/编码精确匹配优先，其次名称/编码/条码模糊匹配。
     *
     * @return Collection<int, Product>
     */
    public function search(string $query, int $limit = 30): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return Product::active()->limit($limit)->get();
        }

        $exact = Product::active()
            ->where(fn ($q) => $q->where('barcode', $query)->orWhere('code', $query))
            ->limit($limit)
            ->get();

        $fuzzy = Product::active()
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "%{$query}%")
                ->orWhere('barcode', 'like', "%{$query}%"))
            ->limit($limit)
            ->get();

        return $exact->merge($fuzzy)->unique('id')->values();
    }

    /**
     * 按输入解析唯一商品：先条码精确，再编码精确，再模糊取首条；找不到返回 null。
     */
    public function match(string $input): ?Product
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $product = Product::active()->where('barcode', $input)->first();
        if ($product) {
            return $product;
        }

        $product = Product::active()->where('code', $input)->first();
        if ($product) {
            return $product;
        }

        return Product::active()
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$input}%")
                ->orWhere('code', 'like', "%{$input}%")
                ->orWhere('barcode', 'like', "%{$input}%"))
            ->first();
    }

    /**
     * 构建车行快照（含追踪方式提示、原始价留痕与税额取数）。
     *
     * @return array<string, mixed>
     */
    public function resolveLine(Product $product, float $qty = 1, float $discountRate = 0, ?string $lotNo = null, ?string $serialNo = null): array
    {
        if ($qty <= 0) {
            throw new PosException("商品 {$product->code} 数量必须大于 0");
        }
        if ($discountRate < 0 || $discountRate > 100) {
            throw new PosException("商品 {$product->code} 折扣率必须在 0~100 之间");
        }

        $traceability = $product->traceability ?? self::TRACE_NONE;

        if ($traceability === self::TRACE_SERIAL && $serialNo === null) {
            $serialNo = null;
        }
        if ($traceability === self::TRACE_SERIAL && $qty > 1 && $serialNo !== null) {
            throw new PosException("商品 {$product->code} 序列号商品一次只能加购一件");
        }

        $unitPrice = (float) $product->list_price;
        $tax = $product->tax;
        $taxRate = $tax ? (float) $tax->rate : 0;
        $standardCost = (float) $product->standard_cost;

        $line = [
            'product_id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'unit_price' => $unitPrice,
            'original_price' => $unitPrice,
            'standard_cost' => $standardCost,
            'tax_rate' => $taxRate,
            'qty' => $qty,
            'discount_rate' => $discountRate,
            'lot_no' => $traceability === self::TRACE_LOT ? $lotNo : null,
            'serial_no' => $traceability === self::TRACE_SERIAL ? $serialNo : null,
            'traceability' => $traceability,
            'requires_lot' => $traceability === self::TRACE_LOT,
            'requires_serial' => $traceability === self::TRACE_SERIAL,
            'price_adjusted' => false,
            'below_cost' => false,
            'line_note' => null,
        ];

        return $this->recalc($line);
    }

    /**
     * 合并车行：同商品累加数量并重算行金额；否则追加新行。
     *
     * @param  array<int, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $context  station_id / session_id / operator_id
     * @return array<int, array<string, mixed>>
     */
    public function mergeLine(array $cart, array $line, array $context = []): array
    {
        $isSerial = ($line['traceability'] ?? self::TRACE_NONE) === self::TRACE_SERIAL;

        foreach ($cart as $index => $existing) {
            if ((int) $existing['product_id'] !== (int) $line['product_id']) {
                continue;
            }

            // 批次/序列号商品按「商品 + 批次 + 序列号」区分车行，避免不同批次被合并。
            if (($existing['lot_no'] ?? null) !== ($line['lot_no'] ?? null)
                || ($existing['serial_no'] ?? null) !== ($line['serial_no'] ?? null)) {
                continue;
            }

            if ($isSerial) {
                throw new PosException("序列号商品 {$line['code']}（{$line['serial_no']}）已在购物车，不能重复加购");
            }

            $before = $this->snapshot($existing);
            $merged = $this->resolveLine(
                Product::findOrFail($line['product_id']),
                (float) $existing['qty'] + (float) $line['qty'],
                (float) $existing['discount_rate'],
                $line['lot_no'] ?? null,
                $line['serial_no'] ?? null
            );
            $cart[$index] = $merged;

            $this->logger->record([
                'module' => 'pos',
                'action' => AuditLog::ACTION_UPDATE,
                'model' => Product::class,
                'record_id' => (int) $line['product_id'],
                'document_no' => $line['code'],
                'field' => 'qty',
                'old_value' => $before,
                'new_value' => $this->snapshot($merged),
                'message' => "POS 加购合并：{$line['code']} 数量 {$before['qty']} → {$merged['qty']}",
                'context' => $this->auditContext($context),
            ]);

            return $cart;
        }

        $cart[] = $line;

        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_CREATE,
            'model' => Product::class,
            'record_id' => (int) $line['product_id'],
            'document_no' => $line['code'],
            'field' => 'line',
            'old_value' => null,
            'new_value' => $this->snapshot($line),
            'message' => "POS 加购：{$line['code']} × {$line['qty']}"
                . (($line['lot_no'] ?? null) ? "（批次 {$line['lot_no']}）" : '')
                . (($line['serial_no'] ?? null) ? "（序列号 {$line['serial_no']}）" : ''),
            'context' => $this->auditContext($context),
        ]);

        return $cart;
    }

    /**
     * 记录前台扫码加购一行（addLine 仅生成快照、未合并时的留痕）。
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $context
     */
    public function logAddLine(Product $product, array $line, array $context = []): void
    {
        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_CREATE,
            'model' => Product::class,
            'record_id' => $product->id,
            'document_no' => $product->code,
            'field' => 'line',
            'old_value' => null,
            'new_value' => $this->snapshot($line),
            'message' => "POS 扫码加购：{$product->code} × {$line['qty']}",
            'context' => $this->auditContext($context),
        ]);
    }

    /**
     * 改行数量。
     *
     * @param  array<int, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $context  station_id / session_id / operator_id
     * @return array<int, array<string, mixed>>
     */
    public function updateQty(array $cart, int $productId, float $qty, ?int $operatorId = null, array $context = [], ?string $lotNo = null, ?string $serialNo = null): array
    {
        [$index, $line] = $this->find($cart, $productId, $lotNo, $serialNo);

        if ($qty <= 0) {
            throw new PosException("商品 {$line['code']} 数量必须大于 0");
        }

        if (($line['requires_serial'] ?? false) && $qty != 1) {
            throw new PosException("商品 {$line['code']} 为序列号商品，数量只能为 1");
        }

        $before = $this->snapshot($line);
        $line['qty'] = $qty;
        $cart[$index] = $this->recalc($line);

        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_UPDATE,
            'model' => Product::class,
            'record_id' => $productId,
            'document_no' => $line['code'],
            'field' => 'qty',
            'old_value' => $before,
            'new_value' => $this->snapshot($cart[$index]),
            'message' => "POS 改行数量：{$line['code']} {$before['qty']} → {$qty}",
            'context' => $this->auditContext($context + ['operator_id' => $operatorId]),
        ]);

        return $cart;
    }

    /**
     * 行内改价：需具备改价权限；低于标准成本标记审批；原始价保留。
     *
     * @param  array<int, array<string, mixed>>  $cart
     * @return array<int, array<string, mixed>>
     */
    public function updatePrice(array $cart, int $productId, float $unitPrice, bool $canAdjust, ?int $operatorId = null, array $context = [], ?string $lotNo = null, ?string $serialNo = null): array
    {
        if (! $canAdjust) {
            throw new PosException('当前账号没有行内改价权限');
        }

        [$index, $line] = $this->find($cart, $productId, $lotNo, $serialNo);

        if ($unitPrice < 0) {
            throw new PosException("商品 {$line['code']} 单价不能为负");
        }

        $before = $this->snapshot($line);
        $line['unit_price'] = $unitPrice;
        $line['price_adjusted'] = abs($unitPrice - (float) $line['original_price']) > 0.0001;
        $line['below_cost'] = $unitPrice < (float) $line['standard_cost'];
        $cart[$index] = $this->recalc($line);

        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_PRICE_CHANGE,
            'model' => Product::class,
            'record_id' => $productId,
            'document_no' => $line['code'],
            'field' => 'unit_price',
            'old_value' => $before,
            'new_value' => $this->snapshot($cart[$index]),
            'message' => "POS 行内改价：{$line['code']} {$before['unit_price']} → {$unitPrice}",
            'context' => $this->auditContext($context + ['operator_id' => $operatorId, 'below_cost' => $cart[$index]['below_cost']]),
        ]);

        return $cart;
    }

    /**
     * 行折扣率（0~100）。
     *
     * @param  array<int, array<string, mixed>>  $cart
     * @return array<int, array<string, mixed>>
     */
    public function applyLineDiscount(array $cart, int $productId, float $discountRate, ?int $operatorId = null, array $context = [], ?string $lotNo = null, ?string $serialNo = null): array
    {
        [$index, $line] = $this->find($cart, $productId, $lotNo, $serialNo);

        if ($discountRate < 0 || $discountRate > 100) {
            throw new PosException("商品 {$line['code']} 折扣率必须在 0~100 之间");
        }

        $before = $this->snapshot($line);
        $line['discount_rate'] = $discountRate;
        $cart[$index] = $this->recalc($line);

        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_DISCOUNT,
            'model' => Product::class,
            'record_id' => $productId,
            'document_no' => $line['code'],
            'field' => 'discount_rate',
            'old_value' => $before,
            'new_value' => $this->snapshot($cart[$index]),
            'message' => "POS 行折扣：{$line['code']} {$before['discount_rate']}% → {$discountRate}%",
            'context' => $this->auditContext($context + ['operator_id' => $operatorId]),
        ]);

        return $cart;
    }

    /**
     * 删行（前后值留痕）。
     *
     * @param  array<int, array<string, mixed>>  $cart
     * @return array<int, array<string, mixed>>
     */
    public function removeLine(array $cart, int $productId, ?int $operatorId = null, array $context = [], ?string $lotNo = null, ?string $serialNo = null): array
    {
        [$index, $line] = $this->find($cart, $productId, $lotNo, $serialNo);

        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_DELETE,
            'model' => Product::class,
            'record_id' => $productId,
            'document_no' => $line['code'],
            'field' => 'line',
            'old_value' => $this->snapshot($line),
            'new_value' => ['value' => null],
            'message' => "POS 购物车删除行：{$line['code']}（数量 {$line['qty']}）",
            'context' => $this->auditContext($context + ['operator_id' => $operatorId]),
        ]);

        array_splice($cart, $index, 1);

        return $cart;
    }

    /**
     * 车行合计（无整单折扣）：数量、商品合计（折后）、行折扣额、税额、应收。
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, float>
     */
    public function totals(array $lines): array
    {
        return $this->orderTotals($lines);
    }

    /**
     * 整单合计：先按行折扣得行小计，再叠加整单折扣率或整单折扣额；
     * 税额按行折后基数计算，整单折扣不再冲减税基。折扣后金额不允许为负。
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, float>
     */
    public function orderTotals(
        array $lines,
        float $orderDiscountRate = 0,
        ?float $orderDiscountAmount = null
    ): array {
        if ($orderDiscountRate < 0 || $orderDiscountRate > 100) {
            throw new PosException('整单折扣率必须在 0~100 之间');
        }

        $totalQty = 0;
        $subtotal = 0;
        $lineDiscount = 0;
        $taxAmount = 0;

        foreach ($lines as $line) {
            $qty = (float) $line['qty'];
            $unitPrice = (float) $line['unit_price'];
            $gross = $qty * $unitPrice;
            $lineSubtotal = (float) $line['subtotal'];

            $totalQty += $qty;
            $subtotal += $lineSubtotal;
            $lineDiscount += $gross - $lineSubtotal;
            $taxAmount += (float) ($line['tax_amount'] ?? 0);
        }

        $subtotal = round($subtotal, 4);
        $taxAmount = round($taxAmount, 4);

        if ($orderDiscountAmount !== null) {
            if ($orderDiscountAmount < 0) {
                throw new PosException('整单折扣额不能为负');
            }
            $orderDiscount = min($subtotal, round((float) $orderDiscountAmount, 4));
        } else {
            $orderDiscount = round($subtotal * $orderDiscountRate / 100, 4);
        }

        $discountable = round($subtotal - $orderDiscount, 4);

        // 整单折扣按折扣后占比同步冲减税额，避免「折后仍按原价计税」导致应收与销项税不一致。
        $taxDiscount = $subtotal > 0 ? round($taxAmount * $orderDiscount / $subtotal, 4) : 0.0;
        $taxAfter = round($taxAmount - $taxDiscount, 4);

        $grandTotal = round($discountable + $taxAfter, 4);

        if ($grandTotal < -0.0001) {
            throw new PosException('折扣后金额不能为负');
        }

        return [
            'total_qty' => round($totalQty, 4),
            'subtotal' => $subtotal,
            'line_discount_amount' => round($lineDiscount, 4),
            'order_discount_amount' => $orderDiscount,
            'discountable' => $discountable,
            'tax_before_discount' => $taxAmount,
            'tax_discount_amount' => $taxDiscount,
            'tax_amount' => $taxAfter,
            'grand_total' => max(0.0, $grandTotal),
        ];
    }

    /**
     * 把整单折扣按行占比分摊到行税额，使订单行税额合计等于订单头税额。
     * 舍入尾差归到最后一行，保证「行合计 = 头合计」。
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, float>  $totals
     * @return array<int, array<string, mixed>>
     */
    public function applyOrderDiscountToLines(array $lines, array $totals): array
    {
        $subtotal = (float) ($totals['subtotal'] ?? 0);
        $discountable = (float) ($totals['discountable'] ?? $subtotal);
        $ratio = $subtotal > 0 ? $discountable / $subtotal : 0.0;
        $targetTax = round((float) ($totals['tax_amount'] ?? 0), 4);

        $allocated = 0.0;
        $lastIndex = null;

        foreach ($lines as $index => $line) {
            $tax = round((float) ($line['tax_amount'] ?? 0) * $ratio, 4);
            $lines[$index]['tax_amount'] = $tax;
            $allocated = round($allocated + $tax, 4);
            $lastIndex = $index;
        }

        if ($lastIndex !== null) {
            $residual = round($targetTax - $allocated, 4);
            if ($residual !== 0.0) {
                $lines[$lastIndex]['tax_amount'] = round((float) $lines[$lastIndex]['tax_amount'] + $residual, 4);
            }
        }

        return $lines;
    }

    /**
     * 客户默认折扣率（0~100），选客户时带出到整单折扣。
     */
    public function customerDefaultDiscount(?Customer $customer): float
    {
        return $customer ? (float) $customer->default_discount_rate : 0.0;
    }

    /**
     * 整单折扣留痕（折扣率/折扣额与重算合计入审计）。
     *
     * @param  array<string, mixed>  $totals
     * @param  array<string, mixed>  $context
     */
    public function logOrderDiscount(float $rate, ?float $amount, array $totals, ?int $operatorId = null, array $context = []): void
    {
        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_DISCOUNT,
            'field' => 'order_discount',
            'old_value' => null,
            'new_value' => [
                'discount_rate' => $rate,
                'discount_amount' => $amount,
                'order_discount_amount' => (float) ($totals['order_discount_amount'] ?? 0),
                'grand_total' => (float) ($totals['grand_total'] ?? 0),
            ],
            'message' => "POS 整单折扣：率 {$rate}%，折扣额 " . ($amount ?? '—') . "，应收 " . ($totals['grand_total'] ?? '—'),
            'context' => $this->auditContext($context + ['operator_id' => $operatorId]),
        ]);
    }

    /**
     * 重算行金额、税额、成本标记。
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function recalc(array $line): array
    {
        $qty = (float) $line['qty'];
        $unitPrice = (float) $line['unit_price'];
        $discountRate = (float) $line['discount_rate'];
        $taxRate = (float) ($line['tax_rate'] ?? 0);

        $gross = $qty * $unitPrice;
        $line['subtotal'] = round($gross * (1 - $discountRate / 100), 4);
        $line['tax_amount'] = round($line['subtotal'] * $taxRate / 100, 4);
        $line['below_cost'] = $unitPrice < (float) $line['standard_cost'];
        $line['price_adjusted'] = abs($unitPrice - (float) $line['original_price']) > 0.0001;

        return $line;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cart
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function find(array $cart, int $productId, ?string $lotNo = null, ?string $serialNo = null): array
    {
        foreach ($cart as $index => $line) {
            if ((int) $line['product_id'] !== $productId) {
                continue;
            }

            // 同一商品可能按批次/序列号拆成多行，定位时必须带上维度，否则会误改第一行。
            if ($lotNo !== null && (string) ($line['lot_no'] ?? '') !== $lotNo) {
                continue;
            }
            if ($serialNo !== null && (string) ($line['serial_no'] ?? '') !== $serialNo) {
                continue;
            }

            return [$index, $line];
        }

        throw new PosException('购物车中不存在该商品');
    }

    /**
     * 审计上下文归一：保留终端、会话、操作员等可追溯字段，空值不落库。
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function auditContext(array $context): array
    {
        $result = [];
        foreach (['station_id', 'session_id', 'operator_id', 'below_cost'] as $key) {
            if (array_key_exists($key, $context) && $context[$key] !== null) {
                $result[$key] = $context[$key];
            }
        }
        return $result;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function snapshot(array $line): array
    {
        return [
            'code' => $line['code'],
            'qty' => (float) $line['qty'],
            'unit_price' => (float) $line['unit_price'],
            'original_price' => (float) $line['original_price'],
            'discount_rate' => (float) $line['discount_rate'],
            'subtotal' => (float) $line['subtotal'],
            'tax_amount' => (float) ($line['tax_amount'] ?? 0),
            'lot_no' => $line['lot_no'] ?? null,
            'serial_no' => $line['serial_no'] ?? null,
            'below_cost' => (bool) $line['below_cost'],
        ];
    }
}
