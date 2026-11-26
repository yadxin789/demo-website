<?php

namespace App\Modules\Shop\Services\Inventory;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\Lot;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 入库服务：登记批次/序列号，写/更新现存量，写入不可变流水（变动前后数量 + 标准成本）。
 * 同一批次合并数量；序列号一货一码，且不允许重复入库。
 */
class InboundService
{
    public function __construct(protected StockService $stock, protected InventoryAuditor $auditor)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, StockMovement>
     */
    public function inbound(array $data): Collection
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
                    throw new InventoryException('序列号追踪商品必须逐号录入序列号');
                }

                $allowSoldReturn = ($data['source_type'] ?? '') === 'sales_return';
                $movements = collect();
                foreach ($serials as $serial) {
                    $this->ensureSerialNotInbound($product->id, (string) $serial, $allowSoldReturn);
                    $movements->push($this->receiveOne($product, $common, null, (string) $serial, 1));
                }

                return $movements;
            }

            $qty = (float) ($data['qty'] ?? 0);
            if ($qty <= 0) {
                throw new InventoryException('入库数量必须大于 0');
            }

            $lotNo = $data['lot_no'] ?? null;
            if ($product->traceability === 'lot' && empty($lotNo)) {
                throw new InventoryException('批次追踪商品必须登记批次号');
            }

            if ($lotNo) {
                $this->registerLot($product, $lotNo, $data, $movementDate);
            }

            return collect([$this->receiveOne($product, $common, $lotNo, null, $qty)]);
        });
    }

    /**
     * 登记/合并批次档案。
     */
    private function registerLot(Product $product, string $lotNo, array $data, Carbon $receiptDate): void
    {
        Lot::firstOrCreate(
            ['product_id' => $product->id, 'lot_no' => $lotNo],
            [
                'supplier_id' => $data['supplier_id'] ?? null,
                'receipt_date' => $receiptDate->toDateString(),
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => Lot::STATUS_ACTIVE,
            ]
        );
    }

    /**
     * 校验序列号可入库：在库（qty>0）一律拒绝；已售出（qty=0）记录仅允许退货来源回库复用。
     */
    private function ensureSerialNotInbound(int $productId, string $serial, bool $allowSoldReturn): void
    {
        $level = StockLevel::where('product_id', $productId)
            ->where('serial_no', $serial)
            ->first();

        if (! $level) {
            return;
        }

        if ((float) $level->qty > 0) {
            throw new InventoryException("序列号 {$serial} 已在库，不能重复入库");
        }

        if (! $allowSoldReturn) {
            throw new InventoryException("序列号 {$serial} 已售出，普通入库不能重复登记；请走退货回库流程");
        }
    }

    /**
     * 写入单条入库维度，返回对应流水。
     *
     * @param  array<string, mixed>  $common
     */
    private function receiveOne(Product $product, array $common, ?string $lotNo, ?string $serialNo, float $qty): StockMovement
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

        $level ??= new StockLevel([
            'product_id' => $product->id,
            'warehouse_id' => $common['warehouse_id'],
            'location_id' => $common['location_id'],
            'lot_no' => $lotNo,
            'serial_no' => $serialNo,
        ]);

        $before = (float) $level->qty;
        $after = $before + $qty;

        $level->qty = $after;
        $level->avg_cost = $common['unit_cost'];
        $level->save();

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $common['warehouse_id'],
            'location_id' => $common['location_id'],
            'lot_no' => $lotNo,
            'serial_no' => $serialNo,
            'direction' => StockMovement::DIRECTION_IN,
            'qty_before' => $before,
            'qty_after' => $after,
            'qty' => $qty,
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
