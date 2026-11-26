<?php

namespace App\Modules\Shop\Database\Seeders;

use App\Modules\Shop\Models\AiDocTemplate;
use App\Modules\Shop\Models\AiModel;
use App\Modules\Shop\Models\AiProvider;
use App\Modules\Shop\Models\AiSetting;
use App\Modules\Shop\Models\AiSkill;
use App\Modules\Shop\Models\Permission;
use App\Modules\Shop\Models\Role;
use Illuminate\Database\Seeder;

class AiSeeder extends Seeder
{
    public function run(): void
    {
        AiSetting::current();

        $presets = AiSetting::providers();
        foreach ($presets as $key => $preset) {
            $provider = AiProvider::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $preset['name'],
                    'base_url' => $preset['base_url'],
                    'enabled' => false,
                ]
            );

            foreach ($preset['models'] as $modelId => $label) {
                AiModel::updateOrCreate(
                    ['provider_id' => $provider->id, 'model_id' => $modelId],
                    ['name' => $label, 'enabled' => true]
                );
            }
        }

        // 默认模型：DeepSeek 通用对话
        $default = AiModel::whereHas('provider', fn ($q) => $q->where('key', 'deepseek'))
            ->where('model_id', 'deepseek-chat')
            ->first();
        $default?->markAsDefault();

        $templates = [
            [
                'name' => '经营分析报告',
                'category' => '经营',
                'description' => '汇总销售额、毛利、库存、应收应付与异常项，形成月度经营分析。',
                'prompt_guide' => "用自然语言提供统计期间与关注重点。\n"
                    . "优先调用 QueryErpOverview / QuerySalesOrders / QueryPosSales / QueryInventory / QueryFinance 获取真实数据。\n"
                    . "结构：经营摘要 → 销售分析 → 毛利与库存 → 应收应付风险 → 下月建议。\n"
                    . "所有金额保留两位小数，数据缺失标注【待补充】。",
                'enabled' => true,
            ],
            [
                'name' => '采购补货建议',
                'category' => '采购',
                'description' => '根据低库存、近期销量与待收货采购单，生成补货清单与优先级。',
                'prompt_guide' => "用自然语言提供目标仓库与补货周期。\n"
                    . "调用 QueryProducts(low_stock=true)、QueryInventory、QueryPurchaseOrders、QuerySalesOrders。\n"
                    . "输出：商品、当前可用库存、建议采购量、建议供应商（如能匹配）、优先级、理由。\n"
                    . "不得编造供应商报价。",
                'enabled' => true,
            ],
            [
                'name' => '应收账款催收清单',
                'category' => '财务',
                'description' => '按逾期天数生成客户催收清单与话术。',
                'prompt_guide' => "用自然语言提供逾期天数门槛与客户范围。\n"
                    . "调用 QueryFinance(type=receivable)，按逾期天数分档。\n"
                    . "输出：客户、发票号、到期日、逾期天数、未收金额、建议动作与催收话术。",
                'enabled' => true,
            ],
            [
                'name' => '每日经营简报',
                'category' => '经营',
                'description' => '生成适合站内推送或晨会使用的每日经营简报。',
                'prompt_guide' => "调用 QueryErpOverview、QueryPosSales、QueryProducts(low_stock=true)、QueryFinance。\n"
                    . "输出 300-500 字：昨日/今日销售、订单数、低库存预警、逾期应收、待收货采购、今日建议。",
                'enabled' => true,
            ],
        ];

        foreach ($templates as $template) {
            AiDocTemplate::updateOrCreate(['name' => $template['name']], $template);
        }

        $skills = [
            [
                'name' => '经营总览',
                'slug' => 'erp-overview',
                'type' => 'agent',
                'description' => '查询销售、库存、采购、应收应付等经营总览，适合店长与老板日常问数。',
                'config' => ['tools' => ['QueryErpOverview', 'QueryProducts', 'QueryFinance']],
            ],
            [
                'name' => '库存与采购',
                'slug' => 'inventory-purchase',
                'type' => 'tool',
                'description' => '查询实时库存、低库存商品、采购在途，并给出补货建议。',
                'config' => ['tools' => ['QueryInventory', 'QueryProducts', 'QueryPurchaseOrders', 'QueryPartners', 'QuerySalesOrders']],
            ],
            [
                'name' => '销售与收银分析',
                'slug' => 'sales-pos',
                'type' => 'tool',
                'description' => '分析销售订单、POS 收银、客户与热销商品。',
                'config' => ['tools' => ['QuerySalesOrders', 'QueryPosSales', 'QueryProducts', 'QueryPartners']],
            ],
            [
                'name' => '财务与账龄',
                'slug' => 'finance-aging',
                'type' => 'tool',
                'description' => '查询应收应付余额、逾期明细与账龄风险。',
                'config' => ['tools' => ['QueryFinance', 'QueryPartners']],
            ],
            [
                'name' => '文档生成',
                'slug' => 'doc-generator',
                'type' => 'doc-generator',
                'description' => '依据模板和真实经营数据生成经营分析、补货建议、催收清单与每日简报。',
                'config' => ['tools' => ['GetDocTemplate', 'QueryErpOverview', 'QueryProducts', 'QueryInventory', 'QueryFinance']],
            ],
            [
                'name' => '数据变更助理',
                'slug' => 'data-change',
                'type' => 'agent',
                'description' => '按用户指令提交商品改价、采购订单草案的变更提案；需本人在 AI 授权中心确认后才生效。',
                'config' => ['tools' => ['QueryProducts', 'QueryPartners', 'ProposeProductPriceChange', 'ProposePurchaseOrder', 'ListMyProposals']],
            ],
        ];

        foreach ($skills as $skill) {
            AiSkill::updateOrCreate(['slug' => $skill['slug']], $skill);
        }

        // 权限补齐（部署时执行 seeder，旧库也能启用 AI 设置）
        $permission = Permission::updateOrCreate(
            ['code' => 'system.ai.manage'],
            ['name' => 'AI 设置与授权管理', 'module' => 'system', 'type' => 'button']
        );
        Role::where('code', 'super-admin')->first()?->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
