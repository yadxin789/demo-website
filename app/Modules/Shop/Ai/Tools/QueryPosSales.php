<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ErpFormats;
use App\Modules\Shop\Models\PosOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryPosSales implements Tool
{
    use ErpFormats;

    public function description(): Stringable|string
    {
        return '查询 POS 收银销售：按日期、收银员、状态筛选，返回单数、销售额、支付方式与热销商品。';
    }

    public function handle(Request $request): Stringable|string
    {
        $from = $this->parseDate($request['from'] ?? null) ?: now()->startOfDay();
        $to = $this->parseDate($request['to'] ?? null)?->endOfDay() ?: now()->endOfDay();
        $cashier = trim((string) ($request['cashier'] ?? ''));
        $limit = $this->limit($request['limit'] ?? null, 20, 50);

        $query = PosOrder::query()->with(['cashier', 'customer', 'payments.method', 'lines.product'])
            ->whereIn('status', [PosOrder::STATUS_COMPLETED, PosOrder::STATUS_REFUNDED])
            ->whereBetween('order_date', [$from, $to]);

        if ($cashier !== '') {
            $query->whereHas('cashier', fn ($q) => $q->where('name', 'like', "%{$cashier}%"));
        }

        $orders = $query->orderByDesc('order_date')->limit($limit)->get();
        $total = (float) (clone $query)->sum('grand_total');

        if ($orders->isEmpty()) {
            return '该时间范围内没有 POS 销售记录。';
        }

        $out = 'POS 销售：' . $from->format('Y-m-d') . ' 至 ' . $to->format('Y-m-d')
            . '，共 ' . $orders->count() . ' 笔展示样本，合计 ' . $this->money($total) . "：\n";

        foreach ($orders as $order) {
            $methods = $order->payments->map(
                fn ($p) => ($p->method?->name ?: '支付#' . $p->method_id) . ' ' . $this->money($p->amount)
            )->implode('、');
            $out .= '- ' . $order->code . '｜' . $this->dateTime($order->order_date)
                . '｜收银员 ' . ($order->cashier?->name ?: '—')
                . '｜客户 ' . ($order->customer?->name ?: '散客')
                . '｜' . $this->posStatus((string) $order->status)
                . '｜金额 ' . $this->money($order->grand_total)
                . ($methods ? '｜支付 ' . $methods : '');

            if ($order->lines->isNotEmpty()) {
                $out .= "\n  商品：" . $order->lines->map(
                    fn ($l) => ($l->product?->name ?: '商品#' . $l->product_id)
                        . ' × ' . $this->qty($l->qty)
                )->implode('；');
            }
            $out .= "\n";
        }

        return rtrim($out);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('开始日期 YYYY-MM-DD，默认今天'),
            'to' => $schema->string()->description('结束日期 YYYY-MM-DD，默认今天'),
            'cashier' => $schema->string()->description('收银员姓名关键词，可留空'),
            'limit' => $schema->integer()->min(1)->max(50),
        ];
    }
}
