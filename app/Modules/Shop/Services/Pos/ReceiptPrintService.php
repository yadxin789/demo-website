<?php

namespace App\Modules\Shop\Services\Pos;

use App\Modules\Shop\Exceptions\PosException;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\PosStation;
use App\Modules\Shop\Support\EscPosBuilder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * 小票打印服务：把已结账订单组装为 ESC/POS 指令，
 * 经本地打印代理（127.0.0.1）下发打印机与钱箱；代理不可达时抛出可读错误。
 * 浏览器打印备选模板在 P1-517。
 */
class ReceiptPrintService
{
    public function __construct(
        protected HttpFactory $http,
        protected ReceiptTemplateService $templates,
    ) {
    }

    /**
     * 组装小票渲染数据（供 ESC/POS 与浏览器模板复用）。
     * 抬头/地址/电话/页脚与打印项开关取自后台配置的模板（P2-916）。
     */
    public function receiptData(PosOrder $order): array
    {
        $order->loadMissing(['lines.product', 'payments.method', 'cashier', 'customer']);

        $data = [
            'store_name' => config('shop.name', '收银小票'),
            'order_code' => $order->code,
            'order_date' => optional($order->order_date)->format('Y-m-d H:i'),
            'cashier' => $order->cashier?->name ?? '—',
            'customer' => $order->customer?->name ?? '散客',
            'items' => $order->lines->map(fn ($line) => [
                'name' => $line->product?->name ?? '商品已删除',
                'code' => $line->product?->code ?? null,
                'qty' => (float) $line->qty,
                'price' => (float) $line->unit_price,
                'amount' => round((float) $line->qty * (float) $line->unit_price, 2),
            ])->all(),
            'discount' => (float) $order->discount_amount,
            'tax' => (float) $order->tax_amount,
            'total' => (float) $order->grand_total,
            'payments' => $order->payments->map(fn ($payment) => [
                'method' => $payment->method?->name ?? '其他',
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
            ])->all(),
            'change' => (float) $order->change_amount,
        ];

        return $this->templates->apply($data);
    }

    /**
     * 生成 ESC/POS 二进制指令。
     */
    public function buildBytes(PosOrder $order, ?PosStation $station = null): string
    {
        $encoding = $station?->encoding ?? 'gbk';
        $columns = (int) ($station?->paper_width ?? 58) === 80 ? 48 : 32;

        return (new EscPosBuilder($encoding, $columns))
            ->build($this->receiptData($order))
            ->bytes();
    }

    /**
     * 下发打印指令到本地代理；代理不可达或返回失败抛 PosException。
     */
    public function print(PosOrder $order, ?PosStation $station = null): void
    {
        $station ??= $order->station;
        $this->dispatch($this->buildBytes($order, $station), $station);
    }

    /**
     * 向代理下发原始 ESC/POS 二进制指令（测试页等通用入口）。
     */
    public function dispatch(string $bytes, ?PosStation $station = null): void
    {
        $proxy = rtrim((string) config('shop.receipt.proxy_url', 'http://127.0.0.1:9100'), '/');

        try {
            $response = $this->http
                ->withHeaders([
                    'X-Printer-Address' => (string) ($station?->printer_address ?? ''),
                    'X-Printer-Port' => (string) ($station?->printer_port ?? ''),
                    'X-Paper-Width' => (string) ($station?->paper_width ?? 58),
                    'Content-Type' => 'application/octet-stream',
                ])
                ->withBody($bytes, 'application/octet-stream')
                ->post($proxy . '/print');

            if ($response->failed()) {
                throw new PosException("打印代理返回错误（HTTP {$response->status()}）");
            }
        } catch (ConnectionException $e) {
            throw new PosException("无法连接本地打印代理（{$proxy}），请确认打印代理已启动");
        }
    }

    /**
     * 弹出钱箱（走代理脉冲指令）。
     */
    public function openDrawer(?PosStation $station = null): void
    {
        $proxy = rtrim((string) config('shop.receipt.proxy_url', 'http://127.0.0.1:9100'), '/');

        try {
            $response = $this->http->post($proxy . '/cashdrawer', [
                'pin' => $station?->cash_drawer_pin,
            ]);

            if ($response->failed()) {
                throw new PosException('钱箱弹出失败，请检查代理与钱箱连接');
            }
        } catch (ConnectionException $e) {
            throw new PosException("无法连接本地打印代理（{$proxy}），钱箱未弹出");
        }
    }
}
