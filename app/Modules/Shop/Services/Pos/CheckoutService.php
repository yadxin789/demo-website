<?php

namespace App\Modules\Shop\Services\Pos;

use App\Modules\Shop\Exceptions\PosException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\PaymentMethod;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\PosPayment;
use App\Modules\Shop\Models\PosSession;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Inventory\OutboundService;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * POS 结账服务：重算合计、校验多笔支付与终端可用方式、生成订单/行/支付记录，
 * 同步出库扣库存、结转 COGS、生成收入/销项税分录；挂账部分落应收单。
 * 退款红字冲回在 P2-902。
 */
class CheckoutService
{
    public const PAYMENT_CREDIT = 'credit';

    /** 支付方式类型 → 借方会计科目编码。 */
    private const DEBIT_ACCOUNT_MAP = [
        'cash' => '1004',
        'bank' => '1005',
        'card' => '1005',
        'electronic' => '1005',
        'coupon' => '2005',
        'credit' => '1003',
    ];

    public function __construct(
        protected SequenceGenerator $sequences,
        protected CartService $cart,
        protected AuditLogger $logger,
        protected OutboundService $outbound,
        protected EntryService $entries
    ) {
    }

    /**
     * 结账并落库订单、行与支付记录。
     *
     * @param  array<int, array<string, mixed>>  $cart
     * @param  array<int, array<string, mixed>>  $payments
     */
    public function checkout(
        PosSession $session,
        array $cart,
        array $payments,
        ?int $customerId = null,
        float $orderDiscountRate = 0,
        ?float $orderDiscountAmount = null,
        ?string $note = null,
        int $operatorId
    ): PosOrder {
        if (! $session->isOpen()) {
            throw new PosException('当前收银台会话已交班，不能结账');
        }
        if ($cart === []) {
            throw new PosException('购物车为空，不能结账');
        }

        $totals = $this->cart->orderTotals($cart, $orderDiscountRate, $orderDiscountAmount);
        $grandTotal = $totals['grand_total'];

        // 整单折扣同步冲减行税额，保证订单行税额合计与订单头一致。
        $cart = $this->cart->applyOrderDiscountToLines($cart, $totals);

        [$validated, $paidAmount, $hasCredit] = $this->validatePayments(
            $session,
            $payments,
            $grandTotal,
            $customerId
        );

        // 无挂账时实收必须≥应收，差额为找零；有挂账时挂账部分走应收，不找零。
        $changeAmount = 0.0;
        if (! $hasCredit) {
            if ($paidAmount < $grandTotal - 0.0001) {
                throw new PosException('实收金额不足，不能完成结账');
            }
            $changeAmount = round($paidAmount - $grandTotal, 4);
        }

        return DB::transaction(function () use (
            $session, $cart, $validated, $totals, $grandTotal, $paidAmount,
            $changeAmount, $customerId, $note, $operatorId, $hasCredit
        ) {
            $order = PosOrder::create([
                'code' => $this->sequences->generate('pos_receipt'),
                'session_id' => $session->id,
                'station_id' => $session->station_id,
                'cashier_id' => $operatorId,
                'customer_id' => $customerId,
                'order_date' => now(),
                'status' => PosOrder::STATUS_COMPLETED,
                'total_qty' => $totals['total_qty'],
                'subtotal' => $totals['subtotal'],
                'discount_amount' => round(
                    $totals['line_discount_amount'] + $totals['order_discount_amount'], 4
                ),
                'tax_amount' => $totals['tax_amount'],
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'note' => $note,
            ]);

            foreach ($cart as $line) {
                $gross = (float) $line['qty'] * (float) $line['unit_price'];
                $discountAmount = round($gross - (float) $line['subtotal'], 4);

                $order->lines()->create([
                    'product_id' => $line['product_id'] ?? null,
                    'lot_no' => $line['lot_no'] ?? null,
                    'serial_no' => $line['serial_no'] ?? null,
                    'qty' => (float) $line['qty'],
                    'unit_price' => (float) $line['unit_price'],
                    'original_price' => (float) ($line['original_price'] ?? $line['unit_price']),
                    'discount_rate' => (float) ($line['discount_rate'] ?? 0),
                    'discount_amount' => $discountAmount,
                    'tax_id' => $line['tax_id'] ?? null,
                    'tax_amount' => (float) ($line['tax_amount'] ?? 0),
                    'line_note' => $line['line_note'] ?? null,
                ]);
            }

            foreach ($validated as $payment) {
                $paymentRecord = $order->payments()->create([
                    'session_id' => $session->id,
                    'method_id' => $payment['method_id'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'paid_at' => now(),
                    'operator_id' => $operatorId,
                ]);

                $this->logger->record([
                    'module' => 'pos',
                    'action' => AuditLog::ACTION_CREATE,
                    'model' => PosPayment::class,
                    'record_id' => $paymentRecord->id,
                    'document_no' => $order->code,
                    'field' => 'amount',
                    'old_value' => null,
                    'new_value' => [
                        'method_id' => $payment['method_id'],
                        'type' => $payment['type'],
                        'amount' => (float) $payment['amount'],
                        'reference' => $payment['reference'] ?? null,
                    ],
                    'message' => "POS 收款：订单 {$order->code}，{$payment['type']} {$payment['amount']}"
                        . ($payment['reference'] ? "（{$payment['reference']}）" : ''),
                    'context' => [
                        'station_id' => $session->station_id,
                        'session_id' => $session->id,
                        'cashier_id' => $operatorId,
                        'order_id' => $order->id,
                    ],
                ]);
            }

            $this->postSettlement(
                $order,
                $session,
                $validated,
                $totals,
                $changeAmount,
                $customerId,
                $operatorId
            );

            $this->logger->record([
                'module' => 'pos',
                'action' => AuditLog::ACTION_CREATE,
                'model' => PosOrder::class,
                'record_id' => $order->id,
                'document_no' => $order->code,
                'message' => "POS 结账 {$order->code}，应收 {$grandTotal}，实收 {$paidAmount}，找零 {$changeAmount}"
                    . ($hasCredit ? '（含挂账）' : ''),
                'context' => [
                    'station_id' => $session->station_id,
                    'session_id' => $session->id,
                    'cashier_id' => $operatorId,
                    'customer_id' => $customerId,
                    'payment_count' => count($validated),
                    'change_amount' => $changeAmount,
                ],
            ]);

            return $order->load(['lines.product', 'payments.method']);
        });
    }

    /**
     * 校验支付方式、金额、终端可用性与挂账客户；返回规范化支付、实收合计、是否含挂账。
     *
     * @param  array<int, array<string, mixed>>  $payments
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: bool}
     */
    private function validatePayments(PosSession $session, array $payments, float $grandTotal, ?int $customerId): array
    {
        if ($payments === []) {
            throw new PosException('请选择支付方式');
        }

        $validated = [];
        $paidAmount = 0.0;
        $hasCredit = false;

        foreach ($payments as $payment) {
            $methodId = (int) ($payment['method_id'] ?? 0);
            $amount = (float) ($payment['amount'] ?? 0);

            if ($methodId <= 0) {
                throw new PosException('支付方式无效');
            }
            if ($amount <= 0) {
                throw new PosException('支付金额必须大于 0');
            }

            $method = PaymentMethod::active()->find($methodId);
            if (! $method) {
                throw new PosException('支付方式不存在或已停用');
            }
            if (! $session->station->allowsPaymentMethod($methodId)) {
                throw new PosException('当前终端不支持该支付方式');
            }

            $isCredit = $method->type === self::PAYMENT_CREDIT;
            if ($isCredit) {
                if (! $customerId) {
                    throw new PosException('挂账必须选择客户');
                }
                $hasCredit = true;
            }

            $validated[] = [
                'method_id' => $methodId,
                'amount' => $amount,
                'reference' => $payment['reference'] ?? null,
                'type' => $method->type,
            ];
            $paidAmount += $amount;
        }

        return [$validated, round($paidAmount, 4), $hasCredit];
    }

    /**
     * 结账过账：出库扣库存、结转 COGS、生成收入/销项税凭证，挂账落应收单。
     * 全部在结账事务内完成，任一步骤失败回滚整笔订单。
     *
     * @param  array<int, array<string, mixed>>  $payments
     * @param  array<string, mixed>  $totals
     */
    private function postSettlement(
        PosOrder $order,
        PosSession $session,
        array $payments,
        array $totals,
        float $changeAmount,
        ?int $customerId,
        int $operatorId
    ): void {
        $order->load('lines.product');
        $warehouseId = (int) $session->station->warehouse_id;
        $locationId = $session->station->default_location_id
            ? (int) $session->station->default_location_id
            : null;

        // 1) 逐行出库扣库存，并按标准成本收集 COGS 分录。
        $cogsLines = [];
        foreach ($order->lines as $line) {
            $product = $line->product;
            if (! $product) {
                continue;
            }

            $qty = (float) $line->qty;
            $stdCost = (float) $product->standard_cost;

            $this->outbound->outbound([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'qty' => $qty,
                'unit_cost' => $stdCost,
                'lot_no' => $line->lot_no,
                'serials' => $line->serial_no ? [(string) $line->serial_no] : [],
                'source_type' => 'pos_order',
                'source_id' => $order->id,
                'source_code' => $order->code,
                'operator_id' => $operatorId,
                'note' => "POS 销售出库 {$product->code}",
            ]);

            $cogs = round($qty * $stdCost, 4);
            if ($cogs > 0) {
                $memo = "{$product->code} {$qty}";
                $cogsLines[] = ['account_code' => '5001', 'direction' => 'debit', 'amount' => $cogs, 'memo' => $memo];
                $cogsLines[] = ['account_code' => '1001', 'direction' => 'credit', 'amount' => $cogs, 'memo' => $memo];
            }
        }

        // 2) COGS 结转凭证：借 主营业务成本 / 贷 库存商品。
        if ($cogsLines !== []) {
            $journal = Journal::where('code', 'MISC')->firstOrFail();
            $entry = $this->entries->createEntry([
                'journal_id' => $journal->id,
                'entry_date' => $order->order_date?->toDateString(),
                'source_type' => 'pos_order',
                'source_id' => $order->id,
                'source_code' => $order->code,
                'description' => "POS 成本结转 {$order->code}",
                'creator_id' => $operatorId,
            ], $cogsLines);
            $this->entries->post($entry);
        }

        // 3) 收入/销项税凭证：借方按支付方式归集，贷方 收入 + 销项税额。
        $net = round((float) ($totals['discountable'] ?? $totals['subtotal']), 4);
        $tax = round((float) $totals['tax_amount'], 4);

        $debitLines = [];
        $creditAmount = 0.0;
        $cashChangeApplied = false;

        foreach ($payments as $payment) {
            $type = $payment['type'];
            $amount = round((float) $payment['amount'], 4);

            if ($type === self::PAYMENT_CREDIT) {
                $debitLines[] = [
                    'account_code' => self::DEBIT_ACCOUNT_MAP[$type],
                    'direction' => 'debit',
                    'amount' => $amount,
                    'partner_id' => $customerId,
                    'memo' => '挂账应收账款',
                ];
                $creditAmount += $amount;
                continue;
            }

            // 找零由现金收款承担，从现金借方扣减。
            if ($type === 'cash' && $changeAmount > 0 && ! $cashChangeApplied) {
                $amount = round($amount - $changeAmount, 4);
                $cashChangeApplied = true;
            }

            if ($amount <= 0) {
                continue;
            }

            $debitLines[] = [
                'account_code' => self::DEBIT_ACCOUNT_MAP[$type] ?? '1005',
                'direction' => 'debit',
                'amount' => $amount,
                'memo' => $type === 'coupon' ? '预收账款（储值券核销）' : 'POS 收款（' . $type . '）',
            ];
        }

        if ($net > 0) {
            $debitLines[] = ['account_code' => '4001', 'direction' => 'credit', 'amount' => $net, 'memo' => '主营业务收入'];
        }
        if ($tax > 0) {
            $debitLines[] = ['account_code' => '2004', 'direction' => 'credit', 'amount' => $tax, 'memo' => '销项税额'];
        }

        if ($debitLines !== []) {
            $journal = Journal::where('code', 'SALE')->firstOrFail();
            $entry = $this->entries->createEntry([
                'journal_id' => $journal->id,
                'entry_date' => $order->order_date?->toDateString(),
                'source_type' => 'pos_order',
                'source_id' => $order->id,
                'source_code' => $order->code,
                'description' => "POS 销售收款 {$order->code}",
                'creator_id' => $operatorId,
            ], $debitLines);
            $this->entries->post($entry);
        }

        // 4) 挂账部分落应收单，供后续收款核销。
        if ($creditAmount > 0 && $customerId) {
            Invoice::create([
                'code' => $this->sequences->generate('invoice'),
                'partner_id' => $customerId,
                'invoice_date' => $order->order_date?->toDateString(),
                'status' => Invoice::STATUS_OPEN,
                'total_amount' => round($creditAmount, 4),
                'paid_amount' => 0,
                'source_type' => 'pos_order',
                'source_id' => $order->id,
            ]);
        }
    }
}
