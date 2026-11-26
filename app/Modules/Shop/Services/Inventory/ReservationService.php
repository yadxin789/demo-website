<?php

namespace App\Modules\Shop\Services\Inventory;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\SalesOrderLine;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * 库存预留服务：销售订单确认时占用预留量（不扣减实物现存量），
 * 取消订单或发货出库后释放对应预留。预留按商品 + 仓库（批次/序列号精确到行）维度，
 * 行锁内重算可用量，并发预留不超卖。
 */
class ReservationService
{
    public function __construct(protected AuditLogger $logger)
    {
    }

    /** 订单确认：逐行占用预留量。 */
    public function reserve(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('lines.product');

            foreach ($order->lines as $line) {
                $this->adjust($order, $line, 1);
            }

            $this->logger->record([
                'module' => 'sales',
                'action' => AuditLog::ACTION_STOCK_MOVE,
                'model' => SalesOrder::class,
                'record_id' => $order->id,
                'document_no' => $order->code,
                'message' => "销售单 {$order->code} 预留库存",
                'context' => ['line_count' => $order->lines->count()],
            ]);
        });
    }

    /** 释放预留：取消订单或发货出库后调用，回冲占用的预留量。 */
    public function release(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('lines.product');

            foreach ($order->lines as $line) {
                $this->adjust($order, $line, -1);
            }

            $this->logger->record([
                'module' => 'sales',
                'action' => AuditLog::ACTION_STOCK_MOVE,
                'model' => SalesOrder::class,
                'record_id' => $order->id,
                'document_no' => $order->code,
                'message' => "销售单 {$order->code} 释放库存预留",
                'context' => ['line_count' => $order->lines->count()],
            ]);
        });
    }

    /**
     * 逐行调整预留量。sign=1 占用，sign=-1 释放。
     */
    private function adjust(SalesOrder $order, SalesOrderLine $line, int $sign): void
    {
        $qty = (float) $line->qty;
        if ($qty <= 0) {
            return;
        }

        $levels = StockLevel::lockForUpdate()
            ->where('product_id', $line->product_id)
            ->where('warehouse_id', $order->warehouse_id)
            ->when($line->lot_no, fn ($q) => $q->where('lot_no', $line->lot_no))
            ->when($line->serial_no, fn ($q) => $q->where('serial_no', $line->serial_no))
            ->orderBy('id')
            ->get();

        if ($sign > 0) {
            $available = $levels->sum(fn ($level) => max(0.0, (float) $level->qty - (float) $level->reserved_qty));
            if ($available < $qty - 0.0000001) {
                throw new InventoryException(
                    "商品 {$line->product?->code} 可用库存不足：需预留 {$qty}，可用 {$available}"
                );
            }
        }

        $remaining = $qty;

        foreach ($levels as $level) {
            if ($remaining <= 0.0000001) {
                break;
            }

            if ($sign > 0) {
                $usable = max(0.0, (float) $level->qty - (float) $level->reserved_qty);
                $take = min($usable, $remaining);
                if ($take <= 0) {
                    continue;
                }
                $level->reserved_qty = round((float) $level->reserved_qty + $take, 4);
                $remaining -= $take;
            } else {
                $free = min((float) $level->reserved_qty, $remaining);
                if ($free <= 0) {
                    continue;
                }
                $level->reserved_qty = round((float) $level->reserved_qty - $free, 4);
                $remaining -= $free;
            }

            $level->save();
        }
    }
}
