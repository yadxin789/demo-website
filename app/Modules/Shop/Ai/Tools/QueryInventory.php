<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ErpFormats;
use App\Modules\Shop\Models\StockLevel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryInventory implements Tool
{
    use ErpFormats;

    public function description(): Stringable|string
    {
        return '查询实时库存：按商品名称/编码/条码、仓库、库位筛选，返回现存、预留、可用数量与批次/序列号。';
    }

    public function handle(Request $request): Stringable|string
    {
        $keyword = trim((string) ($request['keyword'] ?? ''));
        $warehouse = trim((string) ($request['warehouse'] ?? ''));
        $limit = $this->limit($request['limit'] ?? null, 15, 60);

        $query = StockLevel::query()->with(['product', 'warehouse', 'location']);

        if ($keyword !== '') {
            $query->whereHas('product', function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%")
                    ->orWhere('barcode', 'like', "%{$keyword}%");
            });
        }
        if ($warehouse !== '') {
            $query->whereHas('warehouse', fn ($q) => $q->where('name', 'like', "%{$warehouse}%"));
        }

        $rows = $query->orderByDesc('qty')->limit($limit)->get();

        if ($rows->isEmpty()) {
            return '未找到符合条件的库存记录。';
        }

        $out = "库存查询结果（{$rows->count()} 条）：\n";
        foreach ($rows as $row) {
            $out .= '- ' . ($row->product?->code ?: '—') . '｜' . ($row->product?->name ?: '未知商品')
                . '｜仓库 ' . ($row->warehouse?->name ?: '—')
                . '｜库位 ' . ($row->location?->code ?: '—')
                . ($row->lot_no ? '｜批次 ' . $row->lot_no : '')
                . ($row->serial_no ? '｜序列号 ' . $row->serial_no : '')
                . '｜现存 ' . $this->qty($row->qty)
                . '｜预留 ' . $this->qty($row->reserved_qty)
                . '｜可用 ' . $this->qty($row->availableQty())
                . "\n";
        }

        return rtrim($out);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()->description('商品名称 / 编码 / 条码关键词'),
            'warehouse' => $schema->string()->description('仓库名称关键词，可留空'),
            'limit' => $schema->integer()->min(1)->max(60),
        ];
    }
}
