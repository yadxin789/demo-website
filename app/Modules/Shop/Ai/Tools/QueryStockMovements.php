<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ErpFormats;
use App\Modules\Shop\Models\StockMovement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryStockMovements implements Tool
{
    use ErpFormats;

    public function description(): Stringable|string
    {
        return '查询库存流水：按商品、方向、日期筛选，返回出入库数量、成本、来源单据与操作人。';
    }

    public function handle(Request $request): Stringable|string
    {
        $keyword = trim((string) ($request['keyword'] ?? ''));
        $direction = trim((string) ($request['direction'] ?? ''));
        $from = $this->parseDate($request['from'] ?? null);
        $to = $this->parseDate($request['to'] ?? null)?->endOfDay();
        $limit = $this->limit($request['limit'] ?? null, 15, 60);

        $query = StockMovement::query()->with(['product', 'location', 'operator']);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('source_code', 'like', "%{$keyword}%")
                    ->orWhere('lot_no', 'like', "%{$keyword}%")
                    ->orWhere('serial_no', 'like', "%{$keyword}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%"));
            });
        }
        if ($direction !== '') {
            $query->where('direction', $direction);
        }
        if ($from) {
            $query->where('movement_date', '>=', $from);
        }
        if ($to) {
            $query->where('movement_date', '<=', $to);
        }

        $rows = $query->orderByDesc('movement_date')->orderByDesc('id')->limit($limit)->get();

        if ($rows->isEmpty()) {
            return '未找到符合条件的库存流水。';
        }

        $out = "库存流水（{$rows->count()} 条）：\n";
        foreach ($rows as $row) {
            $out .= '- ' . $this->dateTime($row->movement_date)
                . '｜' . ($row->product?->name ?: '未知商品')
                . '｜' . $row->direction
                . '｜数量 ' . $this->qty($row->qty)
                . '｜结存 ' . $this->qty($row->qty_after)
                . '｜来源 ' . ($row->source_code ?: $row->source_type)
                . '｜操作人 ' . ($row->operator?->name ?: '系统') . "\n";
        }

        return rtrim($out);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()->description('商品 / 单据号 / 批次 / 序列号关键词'),
            'direction' => $schema->string()->description('in / out / transfer / adjust'),
            'from' => $schema->string()->description('开始日期 YYYY-MM-DD'),
            'to' => $schema->string()->description('结束日期 YYYY-MM-DD'),
            'limit' => $schema->integer()->min(1)->max(60),
        ];
    }
}
