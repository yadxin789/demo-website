<?php

namespace App\Modules\Shop\Services\Pos;

use App\Modules\Shop\Models\ReceiptTemplate;

/**
 * 小票模板服务：把后台配置的抬头/地址/电话/页脚与打印项开关，
 * 注入到小票渲染数据中，ESC/POS 与浏览器备选模板共用同一份数据。
 *
 * 页脚支持模板变量：{store_name} {order_code} {order_date} {cashier} {customer} {total} {item_count}。
 */
class ReceiptTemplateService
{
    public const DEFAULT_FOOTER = '谢谢惠顾，欢迎再次光临';

    /** 生效中的模板；未配置时返回内存默认值（不查库写入）。 */
    public function current(): ReceiptTemplate
    {
        $template = ReceiptTemplate::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        return $template ?? new ReceiptTemplate($this->defaults() + ['is_active' => true]);
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'name' => '默认模板',
            'store_name' => (string) config('shop.name', '百货零售'),
            'address' => '',
            'phone' => '',
            'footer' => self::DEFAULT_FOOTER,
            'show_discount' => true,
            'show_cashier' => true,
            'show_barcode' => false,
        ];
    }

    /**
     * 把小票数据与当前模板合并（页脚变量在此完成替换）。
     *
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>
     */
    public function apply(array $receipt): array
    {
        $template = $this->current();
        $storeName = (string) ($template->store_name ?: ($receipt['store_name'] ?? config('shop.name', '百货零售')));

        // 页脚变量取已生效的抬头，避免配置了抬头却被店铺默认名替换
        $vars = $this->variables(array_merge($receipt, ['store_name' => $storeName]));

        return array_merge($receipt, [
            'store_name' => $storeName,
            'address' => (string) ($template->address ?? ''),
            'phone' => (string) ($template->phone ?? ''),
            'footer' => $this->render((string) ($template->footer ?? self::DEFAULT_FOOTER), $vars),
            'show_discount' => (bool) $template->show_discount,
            'show_cashier' => (bool) $template->show_cashier,
            'show_barcode' => (bool) $template->show_barcode,
        ]);
    }

    /**
     * 变量池：数值按两位小数格式化，缺失值为空串。
     *
     * @param  array<string, mixed>  $receipt
     * @return array<string, string>
     */
    public function variables(array $receipt): array
    {
        return [
            'store_name' => (string) ($receipt['store_name'] ?? ''),
            'order_code' => (string) ($receipt['order_code'] ?? ''),
            'order_date' => (string) ($receipt['order_date'] ?? ''),
            'cashier' => (string) ($receipt['cashier'] ?? ''),
            'customer' => (string) ($receipt['customer'] ?? ''),
            'total' => number_format((float) ($receipt['total'] ?? 0), 2, '.', ''),
            'discount' => number_format((float) ($receipt['discount'] ?? 0), 2, '.', ''),
            'tax' => number_format((float) ($receipt['tax'] ?? 0), 2, '.', ''),
            'change' => number_format((float) ($receipt['change'] ?? 0), 2, '.', ''),
            'item_count' => (string) count($receipt['items'] ?? []),
        ];
    }

    /**
     * 替换 {变量}；未知变量保持原样，便于管理员发现拼写错误。
     *
     * @param  array<string, string>  $vars
     */
    public function render(string $text, array $vars): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', function (array $matches) use ($vars) {
            return array_key_exists($matches[1], $vars) ? $vars[$matches[1]] : $matches[0];
        }, $text);
    }

    /**
     * 后台「预览」用样例数据（不依赖真实订单）。
     *
     * @return array<string, mixed>
     */
    public function sample(): array
    {
        return $this->apply([
            'store_name' => (string) config('shop.name', '百货零售'),
            'order_code' => 'RC202611230001',
            'order_date' => now()->format('Y-m-d H:i'),
            'cashier' => '收银员 A',
            'customer' => '散客',
            'items' => [
                ['name' => '可口可乐 330ml', 'code' => 'SKU-001', 'qty' => 2, 'price' => 3.50, 'amount' => 7.00],
                ['name' => '抽纸 3 层', 'code' => 'SKU-002', 'qty' => 1, 'price' => 12.90, 'amount' => 12.90],
            ],
            'discount' => 1.00,
            'tax' => 0.72,
            'total' => 19.62,
            'payments' => [['method' => '现金', 'amount' => 20.00, 'reference' => null]],
            'change' => 0.38,
        ]);
    }
}
