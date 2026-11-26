<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ProposesChanges;
use App\Modules\Shop\Models\AiChangeProposal;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\Supplier;
use App\Modules\Shop\Models\User;
use App\Modules\Shop\Models\Warehouse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProposePurchaseOrder implements Tool
{
    use ProposesChanges;

    public function description(): Stringable|string
    {
        return '当用户明确要求创建采购订单时，生成「待确认采购草稿提案」。不会直接下单；'
            . '需用户本人在 AI 授权中心确认后才创建为草稿采购单。必须提供供应商、仓库与明细（JSON 数组）。';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->currentUser();
        if (! $user instanceof User) {
            return '无法识别当前用户，暂不能提交变更提案。';
        }
        if (! $user->isAdmin() && ! $user->hasPermission('purchase.view')) {
            return '你没有采购管理权限，不能发起采购提案。';
        }

        $supplier = Supplier::find((int) ($request['supplier_id'] ?? 0));
        if (! $supplier) {
            return '供应商不存在，请先用 QueryPartners 查询有效供应商 ID。';
        }
        $warehouse = Warehouse::find((int) ($request['warehouse_id'] ?? 0));
        if (! $warehouse) {
            return '仓库不存在，请确认仓库 ID。';
        }

        $lines = json_decode((string) ($request['lines_json'] ?? ''), true);
        if (! is_array($lines) || $lines === []) {
            return 'lines_json 必须是 JSON 数组，例如 [{"product_id":1,"qty":10,"unit_price":8.5}]。';
        }

        $normalized = [];
        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                return '第 ' . ($index + 1) . ' 行明细格式不正确。';
            }
            $product = Product::find((int) ($line['product_id'] ?? 0));
            if (! $product) {
                return '第 ' . ($index + 1) . ' 行商品不存在。';
            }
            $qty = (float) ($line['qty'] ?? 0);
            $price = (float) ($line['unit_price'] ?? 0);
            if ($qty <= 0 || $price < 0) {
                return '第 ' . ($index + 1) . ' 行数量必须大于 0，单价不能为负。';
            }
            $normalized[] = [
                'product_id' => $product->id,
                'qty' => $qty,
                'unit_price' => $price,
                'discount_rate' => (float) ($line['discount_rate'] ?? 0),
                'tax_id' => isset($line['tax_id']) ? (int) $line['tax_id'] : null,
                'line_note' => $line['line_note'] ?? null,
            ];
        }

        $payload = [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'expected_date' => $request['expected_date'] ?? null,
            'note' => $request['note'] ?? null,
            'lines' => $normalized,
            'reason' => $request['reason'] ?? null,
        ];

        $proposal = $this->submit($user, AiChangeProposal::RESOURCE_PURCHASE_ORDER, 'create', $payload);

        return $this->renderProposal($proposal);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'supplier_id' => $schema->integer()->required()->description('供应商 ID'),
            'warehouse_id' => $schema->integer()->required()->description('收货仓库 ID'),
            'lines_json' => $schema->string()->required()
                ->description('采购明细 JSON 数组：[{"product_id":1,"qty":10,"unit_price":8.5,"tax_id":1}]'),
            'expected_date' => $schema->string()->description('预计到货日期 YYYY-MM-DD'),
            'note' => $schema->string()->description('采购备注'),
            'reason' => $schema->string()->description('创建采购单的业务理由'),
        ];
    }
}
