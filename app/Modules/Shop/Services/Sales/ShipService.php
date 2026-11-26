<?php

namespace App\Modules\Shop\Services\Sales;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Inventory\OutboundService;
use App\Modules\Shop\Services\Inventory\ReservationService;
use Illuminate\Support\Facades\DB;

/**
 * 销售发货服务：确认单逐行出库扣减库存，按标准成本结转主营业务成本，
 * 生成成本分录并过账；库存不足由 OutboundService 拦截。
 */
class ShipService
{
    public const TRACE_LOT = 'lot';
    public const TRACE_SERIAL = 'serial';

    public function __construct(
        protected OutboundService $outbound,
        protected EntryService $entries,
        protected AuditLogger $logger,
        protected ReservationService $reservations
    ) {
    }

    public function ship(SalesOrder $order, ?int $operatorId = null): SalesOrder
    {
        if ($order->status !== SalesOrder::STATUS_CONFIRMED) {
            throw new InventoryException("销售单 {$order->code} 非已确认状态，不能发货");
        }

        return DB::transaction(function () use ($order, $operatorId) {
            $order->load('lines.product');
            $entryLines = [];

            foreach ($order->lines as $line) {
                $product = $line->product;
                $qty = (float) $line->qty;
                $stdCost = (float) ($product?->standard_cost ?? 0);
                $cogs = round($qty * $stdCost, 4);

                $payload = [
                    'product_id' => $product->id,
                    'warehouse_id' => $order->warehouse_id,
                    'qty' => $qty,
                    'unit_cost' => $stdCost,
                    'source_type' => 'sales_order',
                    'source_id' => $order->id,
                    'source_code' => $order->code,
                    'operator_id' => $operatorId ?? $order->creator_id,
                    'note' => "销售发货 {$product->code}",
                ];

                // 追踪商品按行指定的批次/序列号定向出库，避免从其他批次串货。
                if ($product->traceability === ShipService::TRACE_SERIAL) {
                    $serial = trim((string) $line->serial_no);
                    if ($serial === '') {
                        throw new InventoryException("商品 {$product->code} 为序列号追踪，发货行必须指定序列号");
                    }
                    if ($qty != 1) {
                        throw new InventoryException("商品 {$product->code} 序列号发货须逐件指定，数量只能为 1");
                    }
                    $payload['serials'] = [$serial];
                } elseif ($product->traceability === ShipService::TRACE_LOT) {
                    $lotNo = trim((string) $line->lot_no);
                    if ($lotNo === '') {
                        throw new InventoryException("商品 {$product->code} 为批次追踪，发货行必须指定批次号");
                    }
                    $payload['lot_no'] = $lotNo;
                }

                $this->outbound->outbound($payload);

                if ($cogs > 0) {
                    $memo = "{$product->code} {$qty}";
                    $entryLines[] = ['account_code' => '5001', 'direction' => 'debit', 'amount' => $cogs, 'memo' => $memo];
                    $entryLines[] = ['account_code' => '1001', 'direction' => 'credit', 'amount' => $cogs, 'memo' => $memo];
                }
            }

            if ($entryLines !== []) {
                $journal = Journal::where('code', 'MISC')->firstOrFail();
                $entry = $this->entries->createEntry([
                    'journal_id' => $journal->id,
                    'entry_date' => $order->order_date?->toDateString(),
                    'source_type' => 'sales_order',
                    'source_id' => $order->id,
                    'source_code' => $order->code,
                    'description' => "销售成本结转 {$order->code}",
                ], $entryLines);
                $this->entries->post($entry);
            }

            // 实物已出库，释放订单占用的预留量。
            $this->reservations->release($order);

            $order->status = SalesOrder::STATUS_SHIPPED;
            $order->save();

            $this->logger->record([
                'module' => 'sales',
                'action' => AuditLog::ACTION_STOCK_MOVE,
                'model' => SalesOrder::class,
                'record_id' => $order->id,
                'document_no' => $order->code,
                'message' => "销售单 {$order->code} 发货出库",
                'context' => ['line_count' => $order->lines->count()],
            ]);

            return $order->load('lines.product');
        });
    }
}
