<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ErpFormats;
use App\Modules\Shop\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryProducts implements Tool
{
    use ErpFormats;

    public function description(): Stringable|string
    {
        return '查询商品资料：支持按名称、商品编码、条码、分类筛选，也可只看低库存商品；返回售价、成本、可用库存。';
    }

    public function handle(Request $request): Stringable|string
    {
        $keyword = trim((string) ($request['keyword'] ?? ''));
        $category = trim((string) ($request['category'] ?? ''));
        $onlyLowStock = (bool) ($request['low_stock'] ?? false);
        $limit = $this->limit($request['limit'] ?? null, 10, 50);

        $query = Product::query()
            ->with(['category', 'unit'])
            ->withSum('stockLevels as stock_qty', 'qty')
            ->withSum('stockLevels as reserved_qty', 'reserved_qty')
            ->where('is_active', true);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%")
                    ->orWhere('barcode', 'like', "%{$keyword}%");
            });
        }
        if ($category !== '') {
            $query->whereHas('category', fn ($q) => $q->where('name', 'like', "%{$category}%"));
        }

        $products = $query->orderBy('name')->limit($limit * 3)->get();

        if ($onlyLowStock) {
            $products = $products->filter(
                fn (Product $p) => ((float) $p->stock_qty - (float) $p->reserved_qty) <= 10
            )->values();
        }

        $products = $products->take($limit);

        if ($products->isEmpty()) {
            return '未找到符合条件的商品。';
        }

        $out = "商品查询结果（{$products->count()} 条）：\n";
        foreach ($products as $p) {
            $available = (float) $p->stock_qty - (float) $p->reserved_qty;
            $out .= '- ' . $p->code . '｜' . $p->name
                . '｜条码 ' . ($p->barcode ?: '—')
                . '｜分类 ' . ($p->category?->name ?: '—')
                . '｜售价 ' . $this->money($p->list_price)
                . '｜平均成本 ' . $this->money($p->avg_cost)
                . '｜可用库存 ' . $this->qty($available) . ' ' . ($p->unit?->name ?: '')
                . "\n";
        }

        return rtrim($out);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()->description('商品名称 / 编码 / 条码关键词，可留空'),
            'category' => $schema->string()->description('商品分类名称，可留空'),
            'low_stock' => $schema->boolean()->description('是否只看可用库存 ≤ 10 的商品'),
            'limit' => $schema->integer()->min(1)->max(50),
        ];
    }
}
