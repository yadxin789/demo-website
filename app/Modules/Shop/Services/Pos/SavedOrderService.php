<?php

namespace App\Modules\Shop\Services\Pos;

use App\Modules\Shop\Exceptions\PosException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\PosSavedOrder;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;

/**
 * 挂单/取单服务：购物车快照序列化保存（不扣库存），取单时校验归属与有效期，
 * 并确认商品仍在售；过期挂单不可取，可批量清理。
 */
class SavedOrderService
{
    public function __construct(protected AuditLogger $logger)
    {
    }

    /**
     * 保存挂单快照。
     *
     * @param  array<int, array<string, mixed>>  $cart
     */
    public function save(
        int $stationId,
        int $cashierId,
        array $cart,
        ?int $customerId = null,
        ?int $ttlMinutes = null,
        ?string $note = null
    ): PosSavedOrder {
        if ($cart === []) {
            throw new PosException('购物车为空，不能挂单');
        }

        $ttl = $ttlMinutes ?? (int) config('shop.pos.saved_order_ttl_minutes', 1440);

        $order = PosSavedOrder::create([
            'station_id' => $stationId,
            'cashier_id' => $cashierId,
            'customer_id' => $customerId,
            'cart_json' => $cart,
            'saved_at' => now(),
            'expired_at' => now()->addMinutes($ttl),
            'note' => $note,
        ]);

        $lineCount = count($cart);

        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_CREATE,
            'model' => PosSavedOrder::class,
            'record_id' => $order->id,
            'document_no' => (string) $order->id,
            'message' => "POS 挂单 #{$order->id}（{$lineCount} 行）",
            'context' => ['station_id' => $stationId, 'cashier_id' => $cashierId],
        ]);

        return $order;
    }

    /**
     * 当前收银员在本终端的有效挂单。
     *
     * @return Collection<int, PosSavedOrder>
     */
    public function listValid(int $stationId, int $cashierId): Collection
    {
        return PosSavedOrder::valid()
            ->where('station_id', $stationId)
            ->where('cashier_id', $cashierId)
            ->orderByDesc('saved_at')
            ->get();
    }

    /**
     * 取单：校验归属、有效期与商品有效性，返回挂单记录与车行快照。
     *
     * @return array{order: PosSavedOrder, cart: array<int, array<string, mixed>>}
     */
    public function restore(int $id, int $cashierId): array
    {
        $order = PosSavedOrder::where('id', $id)
            ->where('cashier_id', $cashierId)
            ->firstOrFail();

        if ($order->isExpired()) {
            throw new PosException('该挂单已过期，不能取单');
        }

        $cart = $order->cart_json ?? [];

        foreach ($cart as $line) {
            $productId = $line['product_id'] ?? null;
            $product = Product::active()->find($productId);
            if (! $product) {
                $code = $line['code'] ?? '未知';
                throw new PosException("挂单中商品 {$code} 已停用或不存在，请重新选购");
            }
        }

        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_CREATE,
            'model' => PosSavedOrder::class,
            'record_id' => $order->id,
            'document_no' => (string) $order->id,
            'message' => "POS 取单 #{$order->id}（" . count($cart) . " 行）",
            'context' => [
                'station_id' => $order->station_id,
                'cashier_id' => $cashierId,
                'customer_id' => $order->customer_id,
                'line_count' => count($cart),
            ],
        ]);

        return ['order' => $order, 'cart' => $cart];
    }

    /**
     * 清理已过期挂单，返回删除条数。
     */
    public function purgeExpired(): int
    {
        $count = PosSavedOrder::whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->delete();

        if ($count > 0) {
            $this->logger->record([
                'module' => 'pos',
                'action' => AuditLog::ACTION_DELETE,
                'model' => PosSavedOrder::class,
                'message' => "POS 过期挂单批量清理 {$count} 张",
                'context' => ['count' => $count],
            ]);
        }

        return $count;
    }

    /**
     * 删除本人挂单。
     */
    public function delete(int $id, int $cashierId): void
    {
        $order = PosSavedOrder::where('id', $id)
            ->where('cashier_id', $cashierId)
            ->firstOrFail();

        $this->logger->record([
            'module' => 'pos',
            'action' => AuditLog::ACTION_DELETE,
            'model' => PosSavedOrder::class,
            'record_id' => $order->id,
            'document_no' => (string) $order->id,
            'old_value' => [
                'line_count' => count($order->cart_json ?? []),
                'note' => $order->note,
                'saved_at' => optional($order->saved_at)->toDateTimeString(),
            ],
            'new_value' => ['value' => null],
            'message' => "POS 删除挂单 #{$order->id}",
            'context' => [
                'station_id' => $order->station_id,
                'cashier_id' => $cashierId,
            ],
        ]);

        $order->delete();
    }
}
