<?php

namespace App\Modules\Shop\Services\Inventory;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 库位调拨：同仓库内库位间转移。双向流水，总量守恒；批次/序列号随货转移；调出库位不足拦截。
 */
class TransferService
{
    public function __construct(protected StockService $stock, protected InventoryAuditor $auditor)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function transfer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $product = Product::findOrFail($data['product_id']);
            $warehouseId = (int) $data['warehouse_id'];
            $fromLocationId = (int) $data['from_location_id'];
            $toLocationId = (int) $data['to_location_id'];

            if ($fromLocationId === $toLocationId) {
                throw new InventoryException('调出与调入库位不能相同');
            }

            $qty = (float) ($data['qty'] ?? 0);
            if ($qty <= 0) {
                throw new InventoryException('调拨数量必须大于 0');
            }

            $lotNo = $data['lot_no'] ?? null;
            $serialNo = $data['serial_no'] ?? null;
            $lotNo = is_string($lotNo) ? (trim($lotNo) ?: null) : $lotNo;
            $serialNo = is_string($serialNo) ? (trim($serialNo) ?: null) : $serialNo;
            $movementDate = isset($data['movement_date']) ? Carbon::parse($data['movement_date']) : Carbon::now();
            $unitCost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : (float) $product->standard_cost;

            if (! Location::whereKey($fromLocationId)->where('warehouse_id', $warehouseId)->exists()
                || ! Location::whereKey($toLocationId)->where('warehouse_id', $warehouseId)->exists()) {
                throw new InventoryException('调拨库位不属于指定仓库');
            }

            if ($product->traceability === 'lot' && $lotNo === null) {
                throw new InventoryException("商品 {$product->code} 为批次追踪，必须指定批次号");
            }

            if ($product->traceability === 'serial') {
                if ($serialNo === null) {
                    throw new InventoryException("商品 {$product->code} 为序列号追踪，必须扫描/录入序列号");
                }
                if (abs($qty - 1) > 0.0000001) {
                    throw new InventoryException('序列号商品每次只能调拨 1 件');
                }
            }

            // 调出
            $fromLevel = $this->lockLevel($product->id, $warehouseId, $fromLocationId, $lotNo, $serialNo);
            if (! $fromLevel) {
                throw new InventoryException("商品 {$product->code} 在调出库位无对应库存");
            }

            $beforeFrom = (float) $fromLevel->qty;
            if ($beforeFrom < $qty) {
                throw new InventoryException("商品 {$product->code} 调出库存不足：现有 {$beforeFrom}，需调 {$qty}");
            }

            $afterFrom = $beforeFrom - $qty;
            $fromLevel->qty = $afterFrom;
            $fromLevel->save();

            // 调入
            $toLevel = $this->lockLevel($product->id, $warehouseId, $toLocationId, $lotNo, $serialNo)
                ?? new StockLevel([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $toLocationId,
                    'lot_no' => $lotNo,
                    'serial_no' => $serialNo,
                ]);

            $beforeTo = (float) $toLevel->qty;
            $afterTo = $beforeTo + $qty;
            $toLevel->qty = $afterTo;
            $toLevel->avg_cost = $unitCost;
            $toLevel->save();

            $common = [
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'lot_no' => $lotNo,
                'serial_no' => $serialNo,
                'unit_cost' => $unitCost,
                'movement_date' => $movementDate,
                'source_type' => $data['source_type'] ?? 'transfer',
                'source_id' => $data['source_id'] ?? null,
                'source_code' => $data['source_code'] ?? null,
                'operator_id' => $data['operator_id'] ?? null,
                'note' => $data['note'] ?? null,
                'created_at' => Carbon::now(),
            ];

            $out = StockMovement::create($common + [
                'location_id' => $fromLocationId,
                'direction' => StockMovement::DIRECTION_OUT,
                'qty_before' => $beforeFrom,
                'qty_after' => $afterFrom,
                'qty' => -$qty,
            ]);

            $in = StockMovement::create($common + [
                'location_id' => $toLocationId,
                'direction' => StockMovement::DIRECTION_IN,
                'qty_before' => $beforeTo,
                'qty_after' => $afterTo,
                'qty' => $qty,
            ]);

            $this->auditor->logMovement($out);
            $this->auditor->logMovement($in);

            return ['out' => $out, 'in' => $in];
        });
    }

    private function lockLevel(
        int $productId,
        int $warehouseId,
        ?int $locationId,
        ?string $lotNo,
        ?string $serialNo
    ): ?StockLevel {
        return StockLevel::lockForUpdate()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when($locationId !== null,
                fn ($query) => $query->where('location_id', $locationId),
                fn ($query) => $query->whereNull('location_id'))
            ->where(function ($query) use ($lotNo, $serialNo) {
                $lotNo === null ? $query->whereNull('lot_no') : $query->where('lot_no', $lotNo);
                $serialNo === null ? $query->whereNull('serial_no') : $query->where('serial_no', $serialNo);
            })
            ->first();
    }
}
