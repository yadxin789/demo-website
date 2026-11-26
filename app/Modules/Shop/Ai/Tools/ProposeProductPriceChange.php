<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ProposesChanges;
use App\Modules\Shop\Models\AiChangeProposal;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProposeProductPriceChange implements Tool
{
    use ProposesChanges;

    public function description(): Stringable|string
    {
        return '当用户明确要求调整某个商品售价时，生成「待确认变更提案」。不会直接修改商品价格；'
            . '需用户本人在 AI 授权中心确认后才生效。必须先确认用户提出了明确改价请求，不能自行猜测价格。';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->currentUser();
        if (! $user instanceof User) {
            return '无法识别当前用户，暂不能提交变更提案。';
        }
        if (! $user->isAdmin() && ! $user->hasPermission('master.view')) {
            return '你没有商品资料修改权限，不能发起改价提案。';
        }

        $productId = (int) ($request['product_id'] ?? 0);
        $product = Product::find($productId);
        if (! $product) {
            return "未找到商品 ID {$productId}。";
        }

        $price = round((float) ($request['list_price'] ?? 0), 4);
        if ($price <= 0) {
            return '新售价必须大于 0。';
        }
        if (abs($price - (float) $product->list_price) < 0.0001) {
            return '新售价与当前售价相同，无需变更。';
        }

        $proposal = $this->submit($user, AiChangeProposal::RESOURCE_PRODUCT, 'update_price', [
            'list_price' => $price,
            'reason' => $request['reason'] ?? null,
        ], $product);

        return $this->renderProposal($proposal);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->required()->description('商品 ID，可先用 QueryProducts 查到'),
            'list_price' => $schema->number()->required()->description('拟调整后的销售单价，必须大于 0'),
            'reason' => $schema->string()->description('改价理由，会展示给审批人'),
        ];
    }
}
