<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ErpFormats;
use App\Modules\Shop\Models\SalesOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QuerySalesOrders implements Tool
{
    use ErpFormats;

    public function description(): Stringable|string
    {
        return '查询销售订单：按单号、客户、状态、日期范围筛选，返回订单金额、状态与商品明细。';
    }

    public function handle(Request $request): Stringable|string
    {
        $keyword = trim((string) ($request['keyword'] ?? ''));
        $status = trim((string) ($request['status'] ?? ''));
        $from = $this->parseDate($request['from'] ?? null);
        $to = $this->parseDate($request['to'] ?? null)?->endOfDay();
        $limit = $this->limit($request['limit'] ?? null, 10, 50);

        $query = SalesOrder::query()->with(['customer', 'warehouse', 'lines.product']);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$keyword}%"));
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($from) {
            $query->whereDate('order_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('order_date', '<=', $to);
        }

        $orders = $query->orderByDesc('order_date')->orderByDesc('id')->limit($limit)->get();

        if ($orders->isEmpty()) {
            return '未找到符合条件的销售订单。';
        }

        $out = "销售订单（{$orders->count()} 条）：\n";
        foreach ($orders as $order) {
            $out .= '- ' . $order->code . '｜' . $this->date($order->order_date)
                . '｜客户 ' . ($order->customer?->name ?: '—')
                . '｜' . $this->salesStatus((string) $order->status)
                . '｜数量 ' . $this->qty($order->total_qty)
                . '｜金额 ' . $this->money($order->grand_total)
                . '｜仓库 ' . ($order->warehouse?->name ?: '—');
            if ($order->lines->isNotEmpty()) {
                $out .= "\n  明细：" . $order->lines->map(
                    fn ($l) => ($l->product?->name ?: '商品#' . $l->product_id)
                        . ' × ' . $this->qty($l->qty)
                        . ' @ ' . $this->money($l->unit_price)
                )->implode('；');
            }
            $out .= "\n";
        }

        return rtrim($out);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()->description('销售单号或客户名称关键词'),
            'status' => $schema->string()->description('draft / confirmed / shipped / returned / cancelled'),
            'from' => $schema->string()->description('开始日期 YYYY-MM-DD'),
            'to' => $schema->string()->description('结束日期 YYYY-MM-DD'),
            'limit' => $schema->integer()->min(1)->max(50),
        ];
    }
}
