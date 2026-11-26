<?php

namespace App\Modules\Shop\Services\Inventory;

use App\Modules\Shop\Models\StockLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 库存查询服务。数量唯一来源为 stock_levels；可用量 = 现存量 - 预留量。
 */
class StockService
{
    /**
     * 按过滤条件构造查询。
     *
     * @param  array{product_id?: int, warehouse_id?: int, location_id?: int, lot_no?: ?string, serial_no?: ?string}  $filters
     */
    public function query(array $filters = []): Builder
    {
        $query = StockLevel::query();

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (array_key_exists('lot_no', $filters)) {
            $filters['lot_no'] === null
                ? $query->whereNull('lot_no')
                : $query->where('lot_no', $filters['lot_no']);
        }

        if (array_key_exists('serial_no', $filters)) {
            $filters['serial_no'] === null
                ? $query->whereNull('serial_no')
                : $query->where('serial_no', $filters['serial_no']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, StockLevel>
     */
    public function levels(array $filters = []): Collection
    {
        return $this->query($filters)->get();
    }

    /** 现存量合计。 */
    public function onHandQty(int $productId, array $filters = []): float
    {
        return (float) $this->query(array_merge($filters, ['product_id' => $productId]))
            ->sum('qty');
    }

    /** 可用量合计（现存量 - 预留量）。 */
    public function availableQty(int $productId, array $filters = []): float
    {
        return (float) $this->query(array_merge($filters, ['product_id' => $productId]))
            ->sum(\Illuminate\Support\Facades\DB::raw('qty - reserved_qty'));
    }

    /**
     * 定位唯一库存维度行（不存在返回 null，不自动创建）。
     */
    public function findLevel(
        int $productId,
        int $warehouseId,
        ?int $locationId,
        ?string $lotNo,
        ?string $serialNo
    ): ?StockLevel {
        return $this->query([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'lot_no' => $lotNo,
            'serial_no' => $serialNo,
        ])->first();
    }
}
