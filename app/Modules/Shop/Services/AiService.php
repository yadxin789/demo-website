<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Ai\Tools\GetDocTemplate;
use App\Modules\Shop\Ai\Tools\ListMyProposals;
use App\Modules\Shop\Ai\Tools\ListWorkspaceFiles;
use App\Modules\Shop\Ai\Tools\ProposeProductPriceChange;
use App\Modules\Shop\Ai\Tools\ProposePurchaseOrder;
use App\Modules\Shop\Ai\Tools\QueryErpOverview;
use App\Modules\Shop\Ai\Tools\QueryFinance;
use App\Modules\Shop\Ai\Tools\QueryInventory;
use App\Modules\Shop\Ai\Tools\QueryPartners;
use App\Modules\Shop\Ai\Tools\QueryPosSales;
use App\Modules\Shop\Ai\Tools\QueryProducts;
use App\Modules\Shop\Ai\Tools\QueryPurchaseOrders;
use App\Modules\Shop\Ai\Tools\QuerySalesOrders;
use App\Modules\Shop\Ai\Tools\QueryStockMovements;
use App\Modules\Shop\Ai\Tools\ReadWorkspaceFile;
use App\Modules\Shop\Models\AiModel;
use App\Modules\Shop\Models\AiProvider;
use App\Modules\Shop\Models\AiSetting;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;

/**
 * AI 服务：桥接「DB 中的 AI 配置」与「Laravel AI SDK」。
 * 参考 ERP 的 AiService 设计，但底层使用官方 laravel/ai 包。
 */
class AiService
{
    protected AiSetting $config;

    /**
     * 本次调用使用的模型（多模型支持）。null 表示沿用 ai_settings 的单模型配置。
     */
    protected ?AiModel $model = null;

    public function __construct(?AiSetting $config = null, ?AiModel $model = null)
    {
        $this->config = $config ?: AiSetting::current();
        $this->model = $model;
    }

    /**
     * 指定本次调用使用的模型（链式调用）
     */
    public function useModel(?AiModel $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function model(): ?AiModel
    {
        return $this->model;
    }

    public function config(): AiSetting
    {
        return $this->config;
    }

    public function isAvailable(): bool
    {
        // 多模式下：只要存在任意可用模型即视为可用
        if ($this->resolveModel() !== null) {
            return true;
        }
        return $this->config->isAvailable();
    }

    /**
     * 用户可切换的可用模型列表（模型启用 + 供应商启用且已配 Key）
     */
    public function availableModels()
    {
        return AiModel::usable()->get();
    }

    /**
     * 解析要使用的模型：优先指定 id，否则取默认可用模型
     */
    public function resolveModel(?int $modelId = null): ?AiModel
    {
        if ($modelId) {
            $found = AiModel::with('provider')->where('id', $modelId)->first();
            if ($found && $found->isUsable()) {
                return $found;
            }
        }
        return AiModel::usableDefault();
    }

    /**
     * 当前调用使用的模型标识（如 deepseek-chat）
     */
    protected function modelKey(): ?string
    {
        return $this->model?->model_id ?: ($this->config->model ?: null);
    }

    /**
     * 有图片附件时的看图模型：优先同供应商支持视觉的模型
     */
    protected function visionModelKeyForImage(): ?string
    {
        if ($this->model) {
            if ($this->model->is_vision) {
                return $this->model->model_id;
            }
            $sibling = $this->model->provider?->enabledModels()
                ->where('is_vision', true)
                ->first();
            return $sibling?->model_id;
        }
        // 兼容旧版：ai_settings 中单独配置的视觉模型
        return ((bool) $this->config->vision_enabled && $this->config->vision_model)
            ? $this->config->vision_model
            : null;
    }

    /**
     * 是否支持看图
     */
    public function visionEnabled(): bool
    {
        return $this->visionModelKeyForImage() !== null;
    }

    /**
     * 取「供应商 + 密钥」组合：多模型时按模型所属供应商，否则退回旧版单供应商配置
     *
     * @return array{0: AiProvider|null, 1: string, 2: ?string, 3: ?string} [供应商, lab值, key, baseUrl]
     */
    protected function credentials(): array
    {
        $provider = $this->model?->provider;
        if (!$provider) {
            $provider = AiProvider::findByKey((string) $this->config->provider);
        }

        if ($provider) {
            return [
                $provider,
                $provider->lab(),
                $provider->api_key ?: $this->config->api_key,
                $provider->base_url ?: $this->config->base_url,
            ];
        }

        $info = $this->config->providerInfo();
        return [null, $info['lab'] ?? 'deepseek', $this->config->api_key, $this->config->base_url];
    }

    /**
     * 将 DB 中的提供商配置动态写入 laravel/ai，返回对应的 Lab 枚举。
     */
    protected function applyProviderConfig(): Lab
    {
        [, $labValue, $key, $baseUrl] = $this->credentials();
        $lab = Lab::from($labValue ?: 'deepseek');

        // key 注入
        config(["ai.providers.{$lab->value}.key" => $key]);

        // base_url 注入（openai-compatible 与 deepseek 均支持 url）
        if ($baseUrl) {
            config(["ai.providers.{$lab->value}.url" => rtrim($baseUrl, '/')]);
        }

        return $lab;
    }

    /**
     * PM 智能助手系统提示词
     */
    public function pmInstructions(): string
    {
        return "你是 ERP 进销存与财务管理系统的智能助手。你可以调用工具查询商品、库存、采购、销售、POS、客户、供应商、应收应付与库存流水的真实数据。"
            . "回答用户问题时请使用简洁的中文，优先给结论再给明细。"
            . "所有数字、单号、商品名必须来自工具返回结果，严禁编造；没有数据时明确说明。"
            . "涉及金额时保留两位小数并使用 ¥ 符号。\n\n"
            . "【查询顺序】\n"
            . "1) 经营总览 / 今天卖了多少 / 有哪些风险，先用 QueryErpOverview。\n"
            . "2) 商品、价格、条码、低库存，用 QueryProducts / QueryInventory。\n"
            . "3) 销售单、POS、采购单分别用 QuerySalesOrders / QueryPosSales / QueryPurchaseOrders。\n"
            . "4) 欠款、逾期、账龄用 QueryFinance；客户与供应商基础资料用 QueryPartners。\n"
            . "5) 查出入库轨迹、批次或序列号用 QueryStockMovements。\n"
            . "6) 用户要采购建议时，先查低库存商品与供应商，再给出建议，不要凭空报价。\n\n"
            . "【变更类操作的硬性规则】\n"
            . "1) 你只能调用 Propose* 工具提交「变更提案」，永远不能直接改业务数据。\n"
            . "2) 只有用户本轮明确要求改价或创建采购单时才能调用；提问、分析、报表绝对不能主动调用。\n"
            . "3) 信息不全（商品 ID、供应商、仓库、数量、单价）时先追问，不要猜。\n"
            . "4) 提案返回后必须告诉用户「尚未生效」，需本人到 AI 授权中心确认。\n"
            . "5) 用户问提案进展时用 ListMyProposals 查询，不要凭记忆回答。\n"
            . "6) 工具说没有权限时如实转达，不要尝试绕过。\n\n"
            . "【会话工作区与上传文件】\n"
            . "1) PDF/DOCX/XLSX/PPTX 等附件内容已由系统自动提取并附在消息中，可直接引用。\n"
            . "2) ZIP 已自动解压到工作区，可用 ListWorkspaceFiles 浏览、ReadWorkspaceFile 读取。\n"
            . "3) 不要声称看不到用户上传的文件。\n\n"
            . "【数据边界】\n"
            . "当前系统没有项目、任务、知识库、部门文件等 PM 实体，不要假装能查询这些数据。"
            . "若用户需要外部行情或政策，明确说明系统内没有，并建议用户另行检索。";
    }

    /**
     * 文档生成 Skill 系统提示词
     */
    public function docInstructions(): string
    {
        return "你是 ERP 系统的文档生成专家。用户会用自然语言描述文档所需的数据、参数和内容，你需要：\n"
            . "1) 首先调用 GetDocTemplate，传入 template_id 读取模板、范例、支援文件与生成指引。\n"
            . "2) 涉及经营数据时调用 QueryErpOverview / QuerySalesOrders / QueryPurchaseOrders / QueryInventory / QueryFinance，保证数字真实。\n"
            . "3) 严格按照模板结构与占位符，把用户提供的数据填入模板，生成完整可交付的 Markdown 文档。\n"
            . "4) 用户未提供的占位数据保留并标注【待补充】，不得编造。";
    }

    /**
     * ERP 数据查询工具集
     */
    public function pmTools(): array
    {
        return [
            new QueryErpOverview,
            new QueryProducts,
            new QueryInventory,
            new QuerySalesOrders,
            new QueryPosSales,
            new QueryPurchaseOrders,
            new QueryFinance,
            new QueryPartners,
            new QueryStockMovements,
            new ProposeProductPriceChange,
            new ProposePurchaseOrder,
            new ListMyProposals,
        ];
    }

    /**
     * 文档生成工具集
     */
    public function docTools(): array
    {
        return [
            new GetDocTemplate,
            new QueryErpOverview,
            new QueryProducts,
            new QueryInventory,
            new QuerySalesOrders,
            new QueryPurchaseOrders,
            new QueryFinance,
        ];
    }

    /**
     * 将 history 数组转成 Message 对象列表
     */
    protected function toMessages(array $history): array
    {
        $messages = [];
        foreach ($history as $h) {
            $role = $h['role'] ?? 'user';
            $content = $h['content'] ?? '';
            $messages[] = new Message($role, $content);
        }
        return $messages;
    }

    /**
     * 纯文本对话（无工具）
     */
    public function chat(string $userMessage, ?string $systemPrompt = null, array $history = []): ?string
    {
        if (!$this->isAvailable()) {
            Log::warning('AI 未配置或未启用', ['provider' => $this->config->provider]);
            return null;
        }

        try {
            $lab = $this->applyProviderConfig();
            $agent = new AnonymousAgent($systemPrompt ?: '你是一个智能助手。', $this->toMessages($history), []);
            $response = $agent->prompt($userMessage, provider: $lab, model: $this->modelKey());
            return (string) $response;
        } catch (\Throwable $e) {
            Log::error('AI chat 异常: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    /**
     * 工具注册表：技能/调用方可按名称挑选子集
     *
     * @return array<string, mixed>
     */
    public function toolMap(): array
    {
        return [
            'QueryErpOverview' => new QueryErpOverview,
            'QueryProducts' => new QueryProducts,
            'QueryInventory' => new QueryInventory,
            'QuerySalesOrders' => new QuerySalesOrders,
            'QueryPosSales' => new QueryPosSales,
            'QueryPurchaseOrders' => new QueryPurchaseOrders,
            'QueryFinance' => new QueryFinance,
            'QueryPartners' => new QueryPartners,
            'QueryStockMovements' => new QueryStockMovements,
            'ProposeProductPriceChange' => new ProposeProductPriceChange,
            'ProposePurchaseOrder' => new ProposePurchaseOrder,
            'ListMyProposals' => new ListMyProposals,
        ];
    }

    /**
     * 按工具名取工具实例集合，取不到时回退到全部 PM 工具
     */
    public function toolsForNames(array $names, bool $fallbackToAll = true): array
    {
        $map = $this->toolMap();
        $tools = [];
        foreach ($names as $name) {
            if (isset($map[$name])) {
                $tools[] = $map[$name];
            }
        }
        if (empty($tools) && $fallbackToAll) {
            return $this->pmTools();
        }
        return $tools;
    }

    /**
     * 带工具调用的对话（Function Calling）
     *
     * @param array $history      历史消息数组：[['role'=>'user','content'=>'...'], ...]
     * @param array|null $tools   工具实例列表，null 表示使用全部 PM 工具
     * @param array $attachments  Laravel AI SDK 附件对象（LocalImage / LocalDocument 等）
     * @param string|null $extraInstructions 附加系统指令（如技能要求）
     */
    public function chatWithTools(
        string $userMessage,
        array $history = [],
        ?array $tools = null,
        array $attachments = [],
        ?string $extraInstructions = null
    ): string {
        if (!$this->isAvailable()) {
            return '⚠️ AI 助手尚未配置。请联系管理员在「AI 设置」中配置 API Key 并启用。';
        }

        try {
            $lab = $this->applyProviderConfig();
            $tools = $tools ?: $this->pmTools();

            $instructions = $this->pmInstructions();
            if ($extraInstructions) {
                $instructions .= "\n\n【本次任务的专项要求】\n" . $extraInstructions;
            }

            // 默认使用会话所选模型；有图片附件且具备看图能力时切到视觉模型
            $model = $this->modelKey();
            if (!empty($attachments)) {
                $vision = $this->visionModelKeyForImage();
                if ($vision) {
                    $model = $vision;
                }
            }

            $agent = new AnonymousAgent($instructions, $this->toMessages($history), $tools);
            $response = $agent->prompt($userMessage, $attachments, provider: $lab, model: $model, timeout: 120);
            return (string) $response;
        } catch (\Throwable $e) {
            Log::error('AI chatWithTools 异常: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 'AI 服务异常：' . $e->getMessage();
        }
    }

    /**
     * 自然语言生成文档（任务5 核心）
     *
     * @param int $templateId 文档模板ID
     * @param string $userDescription 用户自然语言描述（数据/参数/内容要求）
     */
    public function generateDocument(int $templateId, string $userDescription): string
    {
        if (!$this->isAvailable()) {
            return '⚠️ AI 尚未配置。请先在 AI 智能助手中配置 API Key 并启用。';
        }

        try {
            $lab = $this->applyProviderConfig();
            $agent = new AnonymousAgent($this->docInstructions(), [], $this->docTools());

            $prompt = "请使用模板ID {$templateId} 生成文档。\n"
                . "用户自然语言描述如下：\n{$userDescription}\n\n"
                . "请先调用 GetDocTemplate 获取模板，再按指引生成完整文档（Markdown）。";

            $response = $agent->prompt($prompt, provider: $lab, model: $this->modelKey(), timeout: 180);
            return (string) $response;
        } catch (\Throwable $e) {
            Log::error('AI generateDocument 异常: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return '文档生成失败：' . $e->getMessage();
        }
    }

    /**
     * 测试连接
     */
    public function testConnection(): array
    {
        if (!$this->config->api_key || str_starts_with((string) $this->config->api_key, 'sk-xxxx')) {
            return ['ok' => false, 'message' => '请先填写 API Key'];
        }

        $result = $this->chat('你好，请只回复四个字：连接成功。');
        if ($result && str_contains($result, '连接成功')) {
            return ['ok' => true, 'message' => '连接成功！AI 回复：' . mb_substr($result, 0, 60)];
        }
        return ['ok' => false, 'message' => '连接失败，请检查 API Key、Base URL 和网络。' . ($result ? '（AI回复：' . mb_substr($result, 0, 60) . '）' : '')];
    }

    /**
     * 测试指定供应商 / 模型的连通性（管理员在设置页逐家验证）
     */
    public function testConnectionFor(?AiProvider $provider = null, ?AiModel $model = null): array
    {
        // 临时切换本次调用的供应商/模型
        $originModel = $this->model;
        if ($model) {
            $this->model = $model->loadMissing('provider');
        } elseif ($provider) {
            $pick = $provider->enabledModels()->first() ?: $provider->models()->first();
            $this->model = $pick ? $pick->loadMissing('provider') : null;
        }

        try {
            [$p, , $key] = $this->credentials();
            if (!$p || !$p->hasRealApiKey()) {
                return ['ok' => false, 'message' => '请先为该供应商填写 API Key'];
            }
            $backupKey = $this->config->api_key;
            $backupUrl = $this->config->base_url;
            // 用供应商自己的凭据覆盖本次调用（不落库）
            $this->config->forceFill(['api_key' => $key, 'base_url' => $p->base_url]);

            try {
                $result = $this->chat('你好，请只回复四个字：连接成功。');
            } finally {
                $this->config->forceFill(['api_key' => $backupKey, 'base_url' => $backupUrl]);
            }

            $which = trim(($p->name ?? '该供应商') . ' / ' . ($this->model?->name ?: ($this->model?->model_id ?: '')));
            if ($result && str_contains($result, '连接成功')) {
                return ['ok' => true, 'message' => '连接成功！' . $which . ' 回复：' . mb_substr($result, 0, 60)];
            }
            return ['ok' => false, 'message' => '连接失败，请检查 API Key、Base URL 与网络。'
                . ($result ? '（回复：' . mb_substr($result, 0, 60) . '）' : '')];
        } finally {
            $this->model = $originModel;
        }
    }

    public function providerInfo(): array
    {
        return $this->config->providerInfo();
    }
}
