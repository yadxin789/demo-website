<?php

namespace App\Modules\Shop\Services\Scan;

use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Support\ScanCode;
use Illuminate\Support\Facades\Cache;

/**
 * 统一扫码解析：按前缀/JSON 优先，其次按商品条码、商品编码、库位条码、库位编码、
 * 序列号、批次号做精确匹配。返回类型与业务对象，供入库、上架、盘点、POS、小票复用。
 */
class ScanResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(string $raw, ?int $warehouseId = null): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new \InvalidArgumentException('扫码内容为空');
        }

        $parsed = ScanCode::parse($raw);
        if ($parsed !== null) {
            return $this->resolveTyped($parsed['type'], $parsed['value'], $warehouseId);
        }

        return $this->resolveRaw($raw, $warehouseId);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTyped(string $type, string $value, ?int $warehouseId): array
    {
        return match ($type) {
            ScanCode::PRODUCT => $this->productResult($this->findProduct($value)),
            ScanCode::LOCATION => $this->locationResult($this->findLocationById($value, $warehouseId)),
            ScanCode::SERIAL => $this->serialResult($value, $warehouseId),
            ScanCode::LOT => $this->lotResult($value, $warehouseId),
            ScanCode::ORDER => $this->orderResult($value),
            ScanCode::PAYMENT => $this->paymentResult($value),
            default => throw new \InvalidArgumentException('不支持的扫码类型'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRaw(string $raw, ?int $warehouseId): array
    {
        // 1) 商品条码精确匹配（零售场景最常见）
        $product = Product::query()->where('barcode', $raw)->first();
        if ($product) {
            return $this->productResult($product);
        }

        // 2) 商品编码精确匹配
        $product = Product::query()->where('code', $raw)->first();
        if ($product) {
            return $this->productResult($product);
        }

        // 3) 库位条码 / 库位编码
        $location = $this->findLocation($raw, $warehouseId);
        if ($location) {
            return $this->locationResult($location);
        }

        // 4) 序列号在库匹配
        $level = StockLevel::query()
            ->where('serial_no', $raw)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->where('qty', '>', 0)
            ->first();
        if ($level) {
            return $this->serialResult($raw, $warehouseId);
        }

        // 5) 批次号在库匹配
        $level = StockLevel::query()
            ->where('lot_no', $raw)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->where('qty', '>', 0)
            ->first();
        if ($level) {
            return $this->lotResult($raw, $warehouseId);
        }

        throw new \InvalidArgumentException("未识别的扫码内容：{$raw}");
    }

    private function findProduct(string $value): Product
    {
        $product = Product::query()
            ->where('code', $value)
            ->orWhere('barcode', $value)
            ->first();

        if (! $product) {
            throw new \InvalidArgumentException("商品不存在：{$value}");
        }

        return $product;
    }

    private function findLocationById(string $value, ?int $warehouseId): Location
    {
        $location = Location::query()
            ->whereKey((int) $value)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->first();

        if (! $location) {
            throw new \InvalidArgumentException("库位不存在：{$value}");
        }

        return $location;
    }

    private function findLocation(string $value, ?int $warehouseId): Location
    {
        $location = Location::query()
            ->where('barcode', $value)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->first();

        if (! $location) {
            $location = Location::query()
                ->where('code', $value)
                ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                ->first();
        }

        if (! $location) {
            throw new \InvalidArgumentException("库位不存在：{$value}");
        }

        return $location;
    }

    /**
     * @return array<string, mixed>
     */
    private function productResult(Product $product): array
    {
        return [
            'ok' => true,
            'type' => ScanCode::PRODUCT,
            'value' => $product->code,
            'label' => $product->name,
            'data' => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'traceability' => $product->traceability,
                'list_price' => (float) $product->list_price,
                'standard_cost' => (float) $product->standard_cost,
                'requires_lot' => $product->traceability === 'lot',
                'requires_serial' => $product->traceability === 'serial',
                'qr_payload' => ScanCode::product($product),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function locationResult(Location $location): array
    {
        return [
            'ok' => true,
            'type' => ScanCode::LOCATION,
            'value' => $location->code,
            'label' => $location->name,
            'data' => [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'barcode' => $location->barcode,
                'warehouse_id' => $location->warehouse_id,
                'qr_payload' => ScanCode::location($location),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialResult(string $serialNo, ?int $warehouseId): array
    {
        $level = StockLevel::query()
            ->where('serial_no', $serialNo)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->where('qty', '>', 0)
            ->first();

        if (! $level) {
            throw new \InvalidArgumentException("序列号不在库：{$serialNo}");
        }

        $product = Product::find($level->product_id);
        if (! $product) {
            throw new \InvalidArgumentException("序列号对应商品不存在：{$serialNo}");
        }

        return [
            'ok' => true,
            'type' => ScanCode::SERIAL,
            'value' => $serialNo,
            'label' => $product->name,
            'data' => [
                'serial_no' => $serialNo,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'warehouse_id' => $level->warehouse_id,
                'location_id' => $level->location_id,
                'lot_no' => $level->lot_no,
                'qty' => (float) $level->qty,
                'qr_payload' => ScanCode::serial($serialNo),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lotResult(string $lotNo, ?int $warehouseId): array
    {
        $level = StockLevel::query()
            ->where('lot_no', $lotNo)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->where('qty', '>', 0)
            ->first();

        if (! $level) {
            throw new \InvalidArgumentException("批次不在库：{$lotNo}");
        }

        $product = Product::find($level->product_id);

        return [
            'ok' => true,
            'type' => ScanCode::LOT,
            'value' => $lotNo,
            'label' => $product?->name ?? $lotNo,
            'data' => [
                'lot_no' => $lotNo,
                'product_id' => $product?->id,
                'product_code' => $product?->code,
                'product_name' => $product?->name,
                'warehouse_id' => $level->warehouse_id,
                'location_id' => $level->location_id,
                'qty' => (float) $level->qty,
                'qr_payload' => ScanCode::lot($lotNo),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderResult(string $orderCode): array
    {
        $order = PosOrder::query()->where('code', $orderCode)->first();

        if (! $order) {
            throw new \InvalidArgumentException("订单不存在：{$orderCode}");
        }

        return [
            'ok' => true,
            'type' => ScanCode::ORDER,
            'value' => $order->code,
            'label' => $order->code,
            'data' => [
                'id' => $order->id,
                'code' => $order->code,
                'grand_total' => (float) $order->grand_total,
                'qr_payload' => ScanCode::order($order->code),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentResult(string $token): array
    {
        $payment = Cache::get('pos_payment_qr:' . $token);

        if (! is_array($payment)) {
            throw new \InvalidArgumentException('付款码已过期或不存在');
        }

        return [
            'ok' => true,
            'type' => ScanCode::PAYMENT,
            'value' => $token,
            'label' => '收款码',
            'data' => $payment + [
                'token' => $token,
                'qr_payload' => ScanCode::payment($token),
            ],
        ];
    }
}
