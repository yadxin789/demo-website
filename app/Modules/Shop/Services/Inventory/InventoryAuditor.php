<?php

namespace App\Modules\Shop\Services\Inventory;

use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\StockMovement;
use App\Modules\Shop\Services\Audit\AuditLogger;

/**
 * 仓储变动审计：入库/出库/调拨/盘点写入流水后，同步留痕到不可删审计日志。
 * 前后数量、成本、库位、批次/序列号、单据号与流水一致。
 */
class InventoryAuditor
{
    public function __construct(protected AuditLogger $logger)
    {
    }

    public function logMovement(StockMovement $movement): void
    {
        $this->logger->record([
            'module' => 'warehouse',
            'action' => AuditLog::ACTION_STOCK_MOVE,
            'model' => Product::class,
            'record_id' => $movement->product_id,
            'document_no' => $movement->source_code,
            'field' => 'qty',
            'old_value' => ['value' => (float) $movement->qty_before],
            'new_value' => ['value' => (float) $movement->qty_after],
            'context' => [
                'movement_id' => $movement->id,
                'warehouse_id' => $movement->warehouse_id,
                'location_id' => $movement->location_id,
                'lot_no' => $movement->lot_no,
                'serial_no' => $movement->serial_no,
                'direction' => $movement->direction,
                'qty' => (float) $movement->qty,
                'unit_cost' => (float) $movement->unit_cost,
                'source_type' => $movement->source_type,
            ],
        ]);
    }
}
