<?php

namespace App\Modules\Shop\Services\Pos;

use App\Modules\Shop\Exceptions\PosException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\PosPayment;
use App\Modules\Shop\Models\PosSession;
use App\Modules\Shop\Models\SalesReturn;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * 交班清点服务：汇总当班销售/各支付方式/折扣，按面额实点现金，
 * 计算应收现金与长短款差异，锁定会话；交班后不可再收银（结账校验已拦截 closed）。
 * 退款红字冲回接 P2-902，本期 total_refund 计 0。
 */
class ShiftService
{
    public const PAYMENT_CASH = 'cash';

    public function __construct(protected AuditLogger $logger)
    {
    }

    /**
     * 当班汇总：笔数、销售额、折扣、退款、各支付方式合计、现金应收。
     *
     * @return array<string, mixed>
     */
    public function summary(PosSession $session): array
    {
        $orders = PosOrder::where('session_id', $session->id)
            ->where('status', PosOrder::STATUS_COMPLETED)
            ->get();

        $payments = PosPayment::where('session_id', $session->id)
            ->with('method')
            ->get();

        $totalSales = (float) $orders->sum('grand_total');
        $totalDiscount = (float) $orders->sum('discount_amount');

        // 当班 POS 订单产生的已过账退货：全部计入退款额，现金退款另计（影响钱箱现金）。
        $refunds = SalesReturn::query()
            ->where('status', SalesReturn::STATUS_DONE)
            ->whereNotNull('pos_order_id')
            ->whereHas('posOrder', fn ($q) => $q->where('session_id', $session->id))
            ->get();

        $totalRefund = round((float) $refunds->sum('refund_amount'), 4);
        $cashRefund = round((float) $refunds
            ->where('refund_method', SalesReturn::REFUND_CASH)
            ->sum('refund_amount'), 4);

        $byMethod = $payments
            ->groupBy(fn (PosPayment $p) => $p->method?->type ?? 'unknown')
            ->map(fn ($group) => round((float) $group->sum('amount'), 4));

        $cashCollected = round((float) ($byMethod->get(self::PAYMENT_CASH, 0) ?? 0), 4);
        $changeAmount = round((float) $orders->sum('change_amount'), 4);

        // 应收现金 = 期初现金 + 现金收款 − 找零付出 − 现金退款。
        $expectedCash = round((float) $session->opened_balance + $cashCollected - $changeAmount - $cashRefund, 4);

        return [
            'order_count' => $orders->count(),
            'total_sales' => round($totalSales, 4),
            'total_discount' => round($totalDiscount, 4),
            'total_refund' => $totalRefund,
            'refund_count' => $refunds->count(),
            'cash_refund' => $cashRefund,
            'payments' => $byMethod->all(),
            'cash_collected' => $cashCollected,
            'change_amount' => $changeAmount,
            'expected_cash' => $expectedCash,
        ];
    }

    /**
     * 交班清点：按面额登记实点现金，计算长短款并锁定会话。
     *
     * @param  array<int, array<string, mixed>>  $denominations  [{denomination, count_qty}]
     */
    public function close(PosSession $session, array $denominations, int $operatorId, ?string $note = null): PosSession
    {
        if (! $session->isOpen()) {
            throw new PosException('该会话已交班，不能重复清点');
        }

        return DB::transaction(function () use ($session, $denominations, $operatorId, $note) {
            $summary = $this->summary($session);

            $countedCash = 0.0;
            foreach ($denominations as $denomination) {
                $denom = round((float) ($denomination['denomination'] ?? 0), 4);
                $qty = (int) ($denomination['count_qty'] ?? 0);

                if ($denom <= 0 || $qty <= 0) {
                    continue;
                }

                $amount = round($denom * $qty, 4);
                $countedCash = round($countedCash + $amount, 4);

                $session->counts()->create([
                    'denomination' => $denom,
                    'count_qty' => $qty,
                    'count_amount' => $amount,
                    'counted_by' => $operatorId,
                    'counted_at' => now(),
                ]);
            }

            $variance = round($countedCash - $summary['expected_cash'], 4);

            $session->update([
                'closed_at' => now(),
                'status' => PosSession::STATUS_CLOSED,
                'expected_cash' => $summary['expected_cash'],
                'counted_cash' => $countedCash,
                'variance_amount' => $variance,
                'total_sales' => $summary['total_sales'],
                'total_refund' => $summary['total_refund'],
                'total_discount' => $summary['total_discount'],
                'note' => $note ?? $session->note,
            ]);

            $varianceLabel = match (true) {
                $variance > 0 => "长款 {$variance}",
                $variance < 0 => "短款 " . abs($variance),
                default => '账实相符',
            };

            $this->logger->record([
                'module' => 'pos',
                'action' => AuditLog::ACTION_UPDATE,
                'model' => PosSession::class,
                'record_id' => $session->id,
                'document_no' => $session->station?->code,
                'message' => "收银台 {$session->station?->code} 交班，应收现金 {$summary['expected_cash']}，实点 {$countedCash}，{$varianceLabel}",
                'context' => [
                    'station_id' => $session->station_id,
                    'cashier_id' => $session->cashier_id,
                    'operator_id' => $operatorId,
                    'order_count' => $summary['order_count'],
                    'total_sales' => $summary['total_sales'],
                    'expected_cash' => $summary['expected_cash'],
                    'counted_cash' => $countedCash,
                    'variance_amount' => $variance,
                ],
            ]);

            return $session->load('counts');
        });
    }
}
