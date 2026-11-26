<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ErpFormats;
use App\Modules\Shop\Models\Customer;
use App\Modules\Shop\Models\Supplier;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryPartners implements Tool
{
    use ErpFormats;

    public function description(): Stringable|string
    {
        return '查询客户或供应商资料：按名称/编码/联系人筛选，返回联系方式、信用额度或合作状态。';
    }

    public function handle(Request $request): Stringable|string
    {
        $type = (string) ($request['type'] ?? 'customer');
        $keyword = trim((string) ($request['keyword'] ?? ''));
        $limit = $this->limit($request['limit'] ?? null, 10, 50);

        if ($type === 'supplier') {
            $query = Supplier::query()->where('is_active', true);
            if ($keyword !== '') {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%")
                    ->orWhere('contact_name', 'like', "%{$keyword}%"));
            }
            $rows = $query->orderBy('name')->limit($limit)->get();

            if ($rows->isEmpty()) {
                return '未找到符合条件的供应商。';
            }

            $out = "供应商（{$rows->count()} 条）：\n";
            foreach ($rows as $row) {
                $out .= '- ' . $row->code . '｜' . $row->name
                    . '｜联系人 ' . ($row->contact_name ?: '—')
                    . '｜电话 ' . ($row->phone ?: '—')
                    . '｜地址 ' . ($row->address ?: '—') . "\n";
            }

            return rtrim($out);
        }

        $query = Customer::query()->where('is_active', true);
        if ($keyword !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('code', 'like', "%{$keyword}%")
                ->orWhere('contact_name', 'like', "%{$keyword}%"));
        }
        $rows = $query->orderBy('name')->limit($limit)->get();

        if ($rows->isEmpty()) {
            return '未找到符合条件的客户。';
        }

        $out = "客户（{$rows->count()} 条）：\n";
        foreach ($rows as $row) {
            $out .= '- ' . $row->code . '｜' . $row->name
                . '｜联系人 ' . ($row->contact_name ?: '—')
                . '｜电话 ' . ($row->phone ?: '—')
                . '｜会员 ' . ($row->membership_level ?: '—')
                . '｜信用额度 ' . $this->money($row->credit_limit) . "\n";
        }

        return rtrim($out);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('customer 或 supplier'),
            'keyword' => $schema->string()->description('名称 / 编码 / 联系人关键词'),
            'limit' => $schema->integer()->min(1)->max(50),
        ];
    }
}
