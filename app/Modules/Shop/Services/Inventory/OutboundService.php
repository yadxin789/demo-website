<?php

namespace App\Modules\Shop\Services\Inventory;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 出库服务：按批次/序列号扣减现存量，按标准成本结转，写入不可变流水；库存不足拦截。
 */
class OutboundService
{
    public function __construct(protected StockService $stock, protected InventoryAuditor $auditor)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, StockMovement>
     */
    public function outbound(array $data): Collection
    {
        return DB::transaction(function () use ($data) {
            $product = Product::findOrFail($data['product_id']);
            $warehouseId = (int) $data['warehouse_id'];
            $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;
            $unitCost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : (float) $product->standard_cost;
            $movementDate = isset($data['movement_date']) ? Carbon::parse($data['movement_date']) : Carbon::now();

            $common = array_merge($data, [
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'unit_cost' => $unitCost,
                'movement_date' => $movementDate,
            ]);

            if ($product->traceability === 'serial') {
                $serials = $data['serials'] ?? [];
                if ($serials === []) {
                    throw new InventoryException('序列号追踪商品必须逐号指定序列号出库');
                }

                $movements = collect();
                foreach ($serials as $serial) {
                    $movements->push($this->releaseOne($product, $common, null, (string) $serial, 1));
                }

                return $movements;
            }

            $qty = (float) ($data['qty'] ?? 0);
            if ($qty <= 0) {
                throw new InventoryException('出库数量必须大于 0');
            }

            $lotNo = $data['lot_no'] ?? null;

            return collect([$this->releaseOne($product, $common, $lotNo, null, $qty)]);
        });
    }

    /**
     * 扣减单条维度库存，返回对应出库流水。
     *
     * @param  array<string, mixed>  $common
     */
    private function releaseOne(Product $product, array $common, ?string $lotNo, ?string $serialNo, float $qty): StockMovement
    {
        $level = StockLevel::lockForUpdate()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $common['warehouse_id'])
            ->when($common['location_id'] !== null,
                fn ($query) => $query->where('location_id', $common['location_id']),
                fn ($query) => $query->whereNull('location_id'))
            ->where(function ($query) use ($lotNo, $serialNo) {
                $lotNo === null ? $query->whereNull('lot_no') : $query->where('lot_no', $lotNo);
                $serialNo === null ? $query->whereNull('serial_no') : $query->where('serial_no', $serialNo);
            })
            ->first();

        if (! $level) {
            throw new InventoryException("商品 {$product->code} 在指定库位/批次/序列号下无库存");
        }

        $before = (float) $level->qty;
        if ($before < $qty) {
            throw new InventoryException("商品 {$product->code} 库存不足：现有 {$before}，需出 {$qty}");
        }

        $after = $before - $qty;
        $level->qty = $after;
        $level->save();

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $common['warehouse_id'],
            'location_id' => $common['location_id'],
            'lot_no' => $lotNo,
            'serial_no' => $serialNo,
            'direction' => StockMovement::DIRECTION_OUT,
            'qty_before' => $before,
            'qty_after' => $after,
            'qty' => -$qty,
            'unit_cost' => $common['unit_cost'],
            'movement_date' => $common['movement_date'],
            'source_type' => $common['source_type'] ?? null,
            'source_id' => $common['source_id'] ?? null,
            'source_code' => $common['source_code'] ?? null,
            'operator_id' => $common['operator_id'] ?? null,
            'note' => $common['note'] ?? null,
            'created_at' => Carbon::now(),
        ]);

        $this->auditor->logMovement($movement);

        return $movement;
    }
}
